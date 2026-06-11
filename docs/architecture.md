# Architecture

## Philosophy

Pragmatic hexagonal architecture for MVP. The goal is **clean layer separation and bounded contexts** — not strict DDD purity. This allows extracting modules to microservices later without breaking changes.

Accepted trade-offs for MVP:
- No Value Objects (use primitives)
- No complex Domain Entities (keep them simple, behavior-free until needed)
- Eloquent models are used in Infrastructure; they never leave that layer
- Repository interfaces live in Domain and are kept, even for simple CRUDs — this is the main migration enabler

---

## Module structure

Every feature lives under `app/Modules/{Module}/` as a bounded context.

```
app/Modules/{Module}/
├── Domain/
│   ├── Entities/        # Simple PHP classes — no framework deps, can grow with behavior
│   └── Contracts/       # Repository interfaces + service/gateway interfaces
├── Application/
│   ├── UseCases/        # One class per operation with execute()
│   ├── Services/        # CRUD orchestration for trivial operations (see below)
│   └── DTOs/            # Input DTOs only (Controller → UseCase)
└── Infrastructure/
    ├── Repositories/    # Eloquent implementations of Domain repository contracts
    ├── Services/        # Internal infrastructure logic (token generation, hashing, etc.)
    └── Gateways/        # Third-party HTTP clients (Socialite, Stripe, Mercado Pago, WhatsApp, etc.)
```

Laravel's HTTP layer stays in `app/Http/` (Controllers, Requests, Resources, Middleware).

---

## Request flow

```
HTTP Request
  → routes/api.php
  → Controller (thin — validates, calls UseCase, returns Resource)
  → FormRequest (validation only)
  → InputDTO (Controller builds this from validated data)
  → UseCase / Service (Application layer — orchestrates)
  → Repository Contract (Domain interface)
  → Repository Implementation (Infrastructure — Eloquent lives here)
  → Entity (Infrastructure maps Eloquent Model → Domain Entity)
  → back up to Controller
  → Laravel Resource (serializes Entity to JSON)
  → ApiResponse::success()
  → JSON
```

**Key rule:** controllers never touch Eloquent directly. Always through a UseCase or Service.

---

## Data flow between layers

| Boundary | What travels |
|---|---|
| Controller → UseCase | `InputDTO` (built from `FormRequest` validated data) |
| UseCase → Controller | Domain `Entity` (simple PHP class) |
| Repository → UseCase | Domain `Entity` (mapped from Eloquent inside Infrastructure) |
| Controller → Resource | Domain `Entity` |

**Why Entities and not output DTOs?**
Entities can grow with behavior (`isAdmin()`, `fullName()`, etc.) without changing any other layer. If you use a plain output DTO, business logic has nowhere to go and ends up polluting UseCases or Resources.

Use an output DTO only for results that are not domain objects — e.g. aggregated report queries (`SalesReportDTO`).

**Eloquent models never leave Infrastructure.** The Repository maps them to Entities before returning.

---

## Same record, multiple module entities

The same physical row can be modeled by more than one Domain Entity when more than one bounded context uses it. This is intentional, not duplication to be eliminated: each module shapes the entity to what its context needs, and they stay decoupled because they share only the Eloquent model (`App\Models\*`), never each other's entities.

The canonical case is the `users` table, modeled by two entities:

| Entity | Context | Carries | Nature |
|---|---|---|---|
| `App\Modules\Auth\Domain\Entities\User` | Authentication | `passwordHash`, `googleId`, `microsoftId`, `isStaff`, `tenantId`; `changePassword()`, `activate()`, `deactivate()`, `isActive()` | Write/lifecycle aggregate |
| `App\Modules\User\Domain\Entities\User` | User directory | identity fields + `roles[]` (RoleAssignment); `getFullName()` | Read model (listing/detail) |

Ownership rule for this split:
- **Auth owns credentials and session lifecycle** — anything about how a user authenticates.
- **The User module owns the person/directory** — listing today, and user create/update/delete when those land. Write behavior for *people* (not credentials) belongs in the User module's entity, not Auth's.

Rules to keep the split safe:
1. A module must never import another module's entity. The only shared point is the Eloquent model each repository maps from.
2. Behavior duplicated across both entities (e.g. `getFullName()`) must stay identical — change it in both, or it is a bug.
3. Both classes are named `User`; when both appear in one file, alias them (`User as UserEntity`, `UserModel`). Prefer not to add a third `User` entity — extend an existing context's entity instead.

---

## UseCase vs Service (Application layer)

Use a **UseCase** when the operation has any logic beyond persistence:
- sends an event or notification
- coordinates multiple repositories
- enforces a business rule
- has side effects

Use a **Service** when the operation is literally `$repo->verb($data)` with nothing else — lookup tables, simple catalogs, configuration records.

Do not default to Services to reduce boilerplate. When in doubt, use a UseCase — it is easier to simplify later than to untangle a fat Service.

---

## Contracts (Domain layer)

Two kinds of contracts live in `Domain/Contracts/`:

**Repository contracts** — abstraction over persistence:
```php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function create(CreateUserDTO $dto): User;
}
```

**Service contracts** — abstraction over external systems:
```php
interface MailerInterface
{
    public function send(string $to, string $subject, string $body): void;
}
```

Implementations live in `Infrastructure/Repositories/` and `Infrastructure/Services/` respectively. Both are bound in a ServiceProvider.

The distinction: Repository = persistence abstraction. Service = external system abstraction.

---

## Dependency injection

Contracts are bound to implementations in `app/Providers/`. UseCases and Services receive contracts via constructor injection — never instantiate concrete implementations directly.

When two UseCases need different implementations of the same contract, use Laravel's contextual binding:

```php
$this->app->when(LoginUseCase::class)
    ->needs(UserRepositoryInterface::class)
    ->give(EloquentUserRepository::class);       // scoped by TenantContext

$this->app->when(StaffLoginUseCase::class)
    ->needs(UserRepositoryInterface::class)
    ->give(EloquentStaffUserRepository::class);  // scoped by is_staff = true
```

This is the pattern for staff vs tenant repositories — not conditional logic inside a shared repository.

---

## Scalability path

Each module is a self-contained bounded context. When a module needs to become a microservice:

1. The Domain layer (Entities + Contracts) becomes the service's public API
2. The Application layer (UseCases) becomes the service's internal logic
3. The Infrastructure layer is replaced with HTTP clients pointing to the new service
4. The Presentation layer (Controllers) swaps the injected contract implementation

No other module is affected because inter-module communication already goes through contracts.

---

## Multi-tenancy

Every request must resolve a tenant context before reaching any UseCase or Repository. This is handled at the middleware layer.

### Tenant resolution

Tenant is resolved from the subdomain on every request:
`{tenant_slug}.kibi.com` → find `Tenant` by slug → bind `TenantContext` with `tenant_id`

The subdomain identifies the **company (tenant)**, not a specific school. School context is resolved inside the application — from the route (`/schools/{schoolUuid}/...`) or from the user's active role assignment.

```php
// TenantMiddleware binds into the container
app()->instance(TenantContext::class, new TenantContext(
    tenantId: $tenant->id,
));

// UseCase receives via constructor injection
public function __construct(
    private readonly TenantContext $context,
    private readonly UserRepositoryInterface $users,
) {}
```

`TenantContext` lives in `app/Common/Tenant/`. It is a per-request singleton — never bind it as a permanent singleton in `AppServiceProvider`.

School-level subdomains (`{school_slug}.kibi.com`) are reserved for public-facing school portals and are not the API entry point.

### Staff exception

Staff routes (`app.kibi.com`) do not go through `TenantMiddleware`, so `TenantContext` is never bound in the container for those requests. Any repository used in a staff UseCase must **not** inject `TenantContext` — instead it scopes by `WHERE is_staff = true`.

This is the only case where two implementations of the same repository interface coexist. Use contextual binding in `AppServiceProvider` to give each UseCase its correct implementation (see Dependency injection section). Do not add conditional logic inside a shared repository to handle both cases.

### Tenant isolation rule

Every Repository method that queries tenant-owned data must apply a tenant scope as the **first** filter. Repositories receive `TenantContext` via constructor injection and apply the scope internally. Controllers and UseCases never apply tenant scoping directly.

For user repositories, the scope matches users who are either the tenant owner (`TenantContext::ownerId`) or have active role assignments within the tenant (via school or tenant-level role). See `EloquentUserRepository::applyTenantScope()`.

For all other tenant-owned tables (`roles`, `schools`, etc.), the scope is the standard `WHERE tenant_id = TenantContext::tenantId`.

### Request flow with tenant resolution

```
HTTP Request ({tenant_slug}.kibi.com)
  → TenantMiddleware (resolves Tenant by slug → binds TenantContext{tenantId, ownerId})
  → routes/api.php
  → Controller → FormRequest → InputDTO
  → UseCase → Repository Contract
  → EloquentRepository (applies tenant scope internally via TenantContext)
  → Entity → Resource → ApiResponse → JSON
```

---

## Roles and permissions

### Two separate systems

**Softlinkia staff** (`users.is_staff = true`):
- Roles have `is_system_role = true` and `tenant_id = NULL`
- Superadmin has no `category_id` — authority is handled by an explicit superadmin check on staff routes (the `Gate::before` owner bypass never fires on staff routes, where `TenantContext` is not bound). The `EnsureStaffSuperadmin` middleware guards staff-management routes.
- All other staff operational roles (Tesorería Operador `operator`, Tesorería Líder `leader` → category `staff/finance`; Soporte `support` → category `staff/support`) have a `category_id` of scope `staff` and manage permissions via `role_permissions` rows
- Access is controlled by domain: staff only accesses `app.kibi.com`
- Repositories scope by `WHERE is_staff = true`

**Tenant users** (`users.is_staff = false`):
- Roles belong to a tenant (`roles.tenant_id`) and always have `is_system_role = false`
- Permissions are managed via `role_permissions` within category bounds
- Access is controlled by subdomain: `{slug}.kibi.com`
- Users belong to a tenant via `user_role_assignments` or by being the tenant owner (`tenants.owner_id`)

There are six distinct role types across both systems (see `docs/database.md` — Role types table). Authority for tenant-admin roles (owner, gestor) is determined by their reserved slug, not by permission checks.

### Owner

Owner is above the permission system. A `Gate::before` bypass applies globally for the user whose `id` matches `TenantContext::ownerId`. The owner identity comes directly from `tenants.owner_id` — no `user_role_assignments` row is needed or consulted for this check.

The bypass is skipped entirely on staff routes because `TenantContext` is never bound in the container for those requests (the gate checks `app()->bound(TenantContext::class)` before resolving it).

Owner never sees a permissions UI. All permission management flows downward from Owner → Gestor → Director.

The `owner` role slug is kept in the seeder for hierarchy reference purposes, but assigning it via `AssignRoleToUserUseCase` is blocked by a domain guard (`OwnerRoleAssignmentException`). The owner bypass is determined solely by `tenants.owner_id`, not by role assignment.

### Role authority — hardcoded actor rules

Role authority is no longer driven by the `hierarchy_level` column at runtime. It is hardcoded in the domain as a static enum. The three actors that can operate on other users are:

```
Owner   > Gestor   > Director
```

**Who can create users:** Owner, Gestor, Director — no one else.
**Who can assign roles to users:** Owner, Gestor, Director — scoped to their school access.
**Who can create custom roles:** Owner and Gestor only. Custom roles are created at the tenant level (`roles.tenant_id`) and their school availability is defined at creation time via `custom_role_schools`. The role's permissions are consistent across all schools it is available in.
**Who can configure `custom_roles_limit`:** Owner only.
**Who can manage permissions on a role:** Owner (any role), Gestor (roles in their schools), Director (roles of users in their school, never gestor roles).

Mutual exclusions enforced at assignment time (hardcoded enum in domain):
- `teacher` and `student` cannot coexist for the same user in the same school.
- `teacher` and `tutor` cannot coexist for the same user in the same school.
- `student` and `tutor` cannot coexist for the same user in the same school.

The `hierarchy_level` column is kept in the schema for future use. See `post-mvp.md`.

### Teacher scope

`teacher_subject_groups` defines the operational scope of a teacher — separate from `user_role_assignments`. The role defines **what** a teacher can do; the assignment defines **where** they can do it.

A teacher only sees groups and subjects where an active row exists (`unassigned_at IS NULL`). Only one active teacher per `subject_id + group_id` is allowed, enforced by a partial unique index.

### Permission category bounds

Every operational role has a `category_id`. Permission categories have a `scope` (`staff`, `tenant`, or `school`) that prevents name collisions across contexts — for example, `staff/finance` and `school/finance` are independent categories with different permission sets.

When assigning a permission to a role, the system validates two things:
1. The permission belongs to the same category as the role (`category_id` match).
2. The category's scope matches the role's context (staff roles only accept `staff`-scoped categories, tenant roles accept `tenant`-scoped, school roles accept `school`-scoped).

This prevents a school teacher role from receiving staff-support permissions, or a tenant finance role from receiving school-director permissions.

Custom roles (`category_id = NULL`, slug not in reserved list) have no category restriction and can receive permissions from any category and scope.

Owner manages all permissions implicitly through the Gate bypass. Gestor manages permissions for roles assigned within their schools. Director manages permissions for roles of users in their school but cannot modify gestor or owner roles.

### Gate setup (AppServiceProvider)

Two gates are registered in `AppServiceProvider::boot()`:

1. **`Gate::before` — Owner bypass**: the user whose `id` matches `TenantContext::ownerId` is granted every ability unconditionally. The gate first checks `app()->bound(TenantContext::class)` — if `TenantContext` is not bound (staff routes), the bypass is skipped entirely. Owner is a tenant concept — staff routes do not use `$this->authorize()` and do not need a gate bypass.

2. **`Gate::after` — Dynamic permission gate**: for all other users, every `$this->authorize('some.slug')` call in a Controller resolves against the effective permission slugs for the current school context. Returns `true` if the permission is found, `null` otherwise. Use `Gate::after` — not `Gate::define('*')` — to avoid overriding policies.

```php
Gate::before(function (User $user, string $ability): ?bool {
    if (! app()->bound(TenantContext::class)) {
        return null;
    }
    $context = app(TenantContext::class);
    if ($context->ownerId === $user->id) {
        return true;
    }
    return null;
});

Gate::after(function (User $user, string $ability): ?bool {
    $schoolId = app()->bound(SchoolContext::class)
        ? app(SchoolContext::class)->schoolId
        : null;
    return $user->hasPermissionTo($ability, $schoolId) ? true : null;
});
```

**Effective permission calculation per school:**
For each active assignment scoped to the current school (or tenant-level assignments where `school_id IS NULL`):
```
effective permissions = role_permissions − user_role_assignment_denials for that assignment
```
All effective permission sets are then unioned across all active assignments for that school.

### Permission query optimization

`User::activeAssignments()` is the single source for all permission checks. It is called by `hasRole()`, `activePermissions()`, and `hasPermissionTo()` — all of which are triggered by every `$this->authorize()` call via the gate.

Two optimizations are applied:

**Eager loading** — loads `role.permissions` in 3 queries total instead of N+2 (one per role):
```php
->with(['role', 'role.permissions'])
```

**Request-level memoization** — the result is cached in a private property on the `User` model. The DB is hit once per request regardless of how many gate checks occur:
```php
private ?Collection $cachedAssignments = null;

public function activeAssignments(): Collection
{
    return $this->cachedAssignments ??= $this->roleAssignments()
        ->whereNull('revoked_at')
        ->with(['role', 'role.permissions', 'denials'])
        ->get();
}
```

The eager load now includes `denials` (the `user_role_assignment_denials` rows) so effective permissions can be computed without additional queries.

Without these optimizations, a single `$this->authorize()` for a non-owner user triggers `activeAssignments()` three times: twice in `Gate::before` (one `hasRole()` per bypass slug) and once in `Gate::after`. With memoization, all three calls hit the cache after the first.

### Request headers for context

| Header | Purpose | Backend effect |
|---|---|---|
| `X-Active-Role` | UI context — which dashboard to render | None. Not read by backend. |
| `X-School-Uuid` | Identifies the school the user is operating in | Resolved by middleware → `SchoolContext` bound in container → Gate applies school-scoped permissions and denials. |

Neither header participates in authentication. `X-School-Uuid` is the only header that affects permission evaluation.

### School context

`SchoolContext` is a per-request value object bound by a middleware that reads the `X-School-Uuid` request header. It is analogous to `TenantContext`:

```php
final class SchoolContext
{
    public function __construct(public readonly int $schoolId) {}
}
```

The middleware resolves the UUID to an internal `school_id`, verifies the school belongs to the current tenant, and binds the instance into the container. When `X-School-Uuid` is absent (tenant-level endpoints), `SchoolContext` is not bound and the Gate operates without school scope, considering only tenant-level assignments (`school_id IS NULL`).

`SchoolContext` lives in `app/Common/School/`. Like `TenantContext`, it is a per-request binding — never register it as a permanent singleton.

### User model permission helpers

| Method | Returns | Purpose |
|---|---|---|
| `hasRole(string $slug): bool` | `bool` | True if ANY active assignment has this role slug |
| `activePermissions(?int $schoolId): array<string>` | `array<string>` | Effective permission slugs for the given school (role permissions − denials). Pass `null` for tenant-level only. |
| `hasPermissionTo(string $slug, ?int $schoolId): bool` | `bool` | True if slug is in `activePermissions($schoolId)` |

### Common/Audit

`app/Common/Audit/AuditLoggerInterface` is the contract UseCases depend on. `AuditLogger` is the concrete implementation — an append-only writer for `audit_logs`. Both live in `app/Common/Audit/` because audit logging is a cross-cutting concern not owned by any single module. The binding is registered in `AppServiceProvider`. Every mutation UseCase injects `AuditLoggerInterface` and calls `$this->audit->log(action, userId, entityId, structBefore, structAfter)`.

---

## Checklist: adding a new module

1. Create `app/Modules/{Module}/Domain/` — Entity, repository/service contracts, domain exceptions
2. Create `app/Modules/{Module}/Application/UseCases/{Action}/` — UseCase with `execute()` + InputDTO
3. Create `app/Modules/{Module}/Infrastructure/Repositories/Eloquent{Module}Repository.php`
4. Register bindings in `app/Providers/AppServiceProvider::register()`
5. Create `app/Http/Controllers/{Module}/`, `app/Http/Requests/{Module}/`, `app/Http/Resources/{Module}/`
6. Register routes in `routes/api.php`
7. Create migration, factory and tests (Unit for Domain, Feature for Controller)
