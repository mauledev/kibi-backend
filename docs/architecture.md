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
- Roles are system roles (`roles.is_system_role = true`)
- Permissions are fixed in code — no `role_permissions` rows
- Access is controlled by domain: staff only accesses `app.kibi.com`
- Repositories scope by `WHERE is_staff = true`

**School users** (`users.is_staff = false`):
- Roles belong to a tenant (`roles.tenant_id`)
- Permissions are dynamic, managed via `role_permissions`
- Access is controlled by subdomain: `{slug}.kibi.com`
- Users belong to a tenant via `user_role_assignments` or by being the tenant owner (`tenants.owner_id`)

### Owner

Owner is above the permission system. A `Gate::before` bypass applies globally for the user whose `id` matches `TenantContext::ownerId`. The owner identity comes directly from `tenants.owner_id` — no `user_role_assignments` row is needed or consulted for this check.

The bypass is skipped entirely on staff routes because `TenantContext` is never bound in the container for those requests (the gate checks `app()->bound(TenantContext::class)` before resolving it).

Owner never sees a permissions UI. All permission management flows downward from Owner → Gestor → Director.

The `owner` role slug is kept in the seeder for hierarchy reference purposes, but assigning it via `AssignRoleToUserUseCase` is blocked by a domain guard (`OwnerRoleAssignmentException`). The owner bypass is determined solely by `tenants.owner_id`, not by role assignment.

### Hierarchy

Role assignment and permission granting are constrained by `hierarchy_level`. A user can only assign roles or grant permissions to roles with a `hierarchy_level` strictly greater than their own.

```
Level 1 → Superadmin           (Softlinkia, is_system_role)
Level 2 → Owner                (Gate::before bypass)
Level 3 → Gestor de Escuelas
Level 4 → Director
Level 5 → Coordinador Académico, Control Escolar
Level 6 → Prefectura, Finanzas, RRHH, Psicopedagogía, Biblioteca
Level 7 → Docente
Level 8 → Alumno, Tutor
Level 9 → Proveedor Cafetería, Proveedor Uniformes
```

### Teacher scope

`teacher_subject_groups` defines the operational scope of a teacher — separate from `user_role_assignments`. The role defines **what** a teacher can do; the assignment defines **where** they can do it.

A teacher only sees groups and subjects where an active row exists (`unassigned_at IS NULL`). Only one active teacher per `subject_id + group_id` is allowed, enforced by a partial unique index.

### manage.permissions

The permission `manage.permissions` allows a role to assign permissions to roles with a higher `hierarchy_level`. Only Gestor, Director and roles explicitly granted this permission can use it. Owner manages permissions implicitly through the Gate bypass.

### Gate setup (AppServiceProvider)

Two gates are registered in `AppServiceProvider::boot()`:

1. **`Gate::before` — Owner bypass**: the user whose `id` matches `TenantContext::ownerId` is granted every ability unconditionally. The gate first checks `app()->bound(TenantContext::class)` — if `TenantContext` is not bound (staff routes), the bypass is skipped entirely. Owner is a tenant concept — staff routes do not use `$this->authorize()` and do not need a gate bypass.

2. **`Gate::after` — Dynamic permission gate**: for all other users, every `$this->authorize('some.slug')` call in a Controller resolves against the merged permission slugs from all active role assignments (`revoked_at IS NULL`). Returns `true` if the permission is found, `null` otherwise (letting any policy take precedence). Use `Gate::after` — not `Gate::define('*')` — to avoid overriding policies.

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
    return $user->hasPermissionTo($ability) ? true : null;
});
```

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
        ->with(['role', 'role.permissions'])
        ->get();
}
```

Without these optimizations, a single `$this->authorize()` for a non-owner user triggers `activeAssignments()` three times: twice in `Gate::before` (one `hasRole()` per bypass slug) and once in `Gate::after`. With memoization, all three calls hit the cache after the first.

### X-Active-Role header

`X-Active-Role` is **UI context only** — the frontend uses it to know which dashboard to render. It does **not** participate in permission checks. All active assignments are always merged.

### User model permission helpers

Four methods added to `App\Models\User`:

| Method | Returns | Purpose |
|---|---|---|
| `hasRole(string $slug): bool` | `bool` | True if ANY active assignment has this role slug |
| `activePermissions(): array<string>` | `array<string>` | Merged permission slugs from all active roles |
| `hasPermissionTo(string $slug): bool` | `bool` | True if slug is in `activePermissions()` |
| `lowestHierarchyLevel(): int` | `int` | Lowest level across active roles (most privileged); `PHP_INT_MAX` when no roles |

### Common/Audit

`app/Common/Audit/AuditLoggerInterface` is the contract UseCases depend on. `AuditLogger` is the concrete implementation — an append-only writer for `audit_logs`. Both live in `app/Common/Audit/` because audit logging is a cross-cutting concern not owned by any single module. The binding is registered in `AppServiceProvider`. Every mutation UseCase injects `AuditLoggerInterface` and calls `$this->audit->log(action, userId, entityId, structBefore, structAfter)`.

Action strings come from the typed catalog in `app/Common/Audit/Events/` — one string-backed enum per module (each implementing `AuditEvent`), aggregated by `AuditEventRegistry`. Prefer passing an enum case (`$this->audit->log(AuthAuditEvent::LOGIN, ...)`); raw `{model}.{verb}` strings are still accepted for backward compatibility. See `docs/audit.md` for the full catalog and what is / isn't audited.

---

## Checklist: adding a new module

1. Create `app/Modules/{Module}/Domain/` — Entity, repository/service contracts, domain exceptions
2. Create `app/Modules/{Module}/Application/UseCases/{Action}/` — UseCase with `execute()` + InputDTO
3. Create `app/Modules/{Module}/Infrastructure/Repositories/Eloquent{Module}Repository.php`
4. Register bindings in `app/Providers/AppServiceProvider::register()`
5. Create `app/Http/Controllers/{Module}/`, `app/Http/Requests/{Module}/`, `app/Http/Resources/{Module}/`
6. Register routes in `routes/api.php`
7. Create migration, factory and tests (Unit for Domain, Feature for Controller)
