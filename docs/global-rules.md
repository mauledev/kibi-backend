# Global Rules

Rules that apply to all code, without exception. Regardless of the task — these rules are always enforced.

---

## PHP

## Naming

| Type | Convention |
|---|---|
| Classes | `PascalCase` |
| Methods and variables | `camelCase` |
| Constants | `UPPER_SNAKE_CASE` |
| Database tables | `snake_case`, plural |
| Database columns | `snake_case` |
| Permission slugs | `{model}.{verb}` — `grade.publish`, `payment.approve` |
| Role slugs | `snake_case` — `school_manager`, `school_registrar` |
| Public IDs in routes | `{uuid}` — never `{id}` |

## User name fields

User names are stored in three separate columns: `first_name`, `last_name_paternal`, `last_name_maternal` (nullable). All API responses expose all four fields including the computed `full_name`:

```json
{
  "first_name": "Mauricio",
  "last_name_paternal": "Ledesma",
  "last_name_maternal": "García",
  "full_name": "Mauricio Ledesma García"
}
```

`full_name` is never stored — it is computed in the Domain Entity (`getFullName()`) and serialized in every Resource. Rationale: separate fields are required for CFDI/RFC generation and official school documents (boletas, constancias).

## UUID generation

UUIDs are always generated in PHP, never by the database. Every Eloquent model that has a `uuid` column must include a `booting()` hook:

```php
protected static function booting(): void
{
    static::creating(function (self $model): void {
        $model->uuid ??= (string) Str::uuid();
    });
}
```

The `??=` preserves any UUID explicitly set by a factory or test. Migration columns declare `->uuid('uuid')->unique()` **without** `->default(DB::raw('gen_random_uuid()'))`. Seeders that bypass Eloquent (raw `DB::table()->insertOrIgnore()`) must include `'uuid' => (string) Str::uuid()` explicitly.

## strict_types

Never add `declare(strict_types=1);` to any PHP file. It is not used in this codebase.

## Docblocks

All public methods require a docblock.

## Formatting and static analysis

PSR-12 is mandatory. Before any commit run:

```bash
docker-compose exec app composer format   # fix formatting
docker-compose exec app composer analyse  # static analysis
```

Or both together: `composer quality`.

## Layers

- Never use Eloquent outside `Infrastructure/Repositories/`
- Controllers never touch Eloquent directly — always through a UseCase or Service
- Business logic lives in Domain; never in controllers, resources or requests

## Responses

Always use `ApiResponse` for JSON responses. Never use `response()->json()` directly.

All response messages (success, error, exception defaults) must be written in **English** — no exceptions. This includes string literals in controllers, exception constructors, and the `Handler`.

## Multi-tenancy

- Every repository method that queries tenant-owned data must scope by `tenant_id` as the **first** filter — a query without this scope is a bug, not a shortcut
- `users.tenant_id` is the primary tenant scope for user queries — always `WHERE tenant_id = X`. Staff users have `tenant_id IS NULL` and `is_staff = true`; they are queried via `EloquentStaffUserRepository` which scopes by `WHERE is_staff = true`.
- `TenantContext` carries only `tenantId` — injected into repositories via constructor. Never resolve the tenant from inside a UseCase or Repository directly.
- Never expose `id` (BIGSERIAL) in any response or route — always use `uuid` (UUID)
- **Read vs write scoping rule (roles)**: read methods (`find*`) use `WHERE tenant_id = X OR tenant_id IS NULL` to include template roles. Mutation methods (`update`, `attachPermission`, `detachPermission`) use `WHERE tenant_id = X` only — a tenant must never modify a template role.

## Roles and permissions

- Owner bypasses all permission checks via `Gate::before` — the gate checks `$user->hasRole('owner')` against `user_role_assignments`. Never add manual owner checks inside UseCases.
- The `owner` role is assigned to the tenant owner via `user_role_assignments` — `AssignRoleToUserUseCase` blocks re-assigning it with `OwnerRoleAssignmentException`
- Softlinkia staff permissions are fixed in code — never add `role_permissions` rows for roles where `is_system_role = true`
- Hierarchy checks (role assignment, permission granting) live in the UseCase layer — never in Controllers or Repositories
- `teacher_subject_groups` is the source of truth for teacher operational scope — never derive teacher access from `user_role_assignments` alone

## Audit logs

- Every action that mutates a domain entity must write to `audit_logs`
- Action naming: `{model}.{verb}` — `grade.publish`, `user.suspend`
- `struct_before` and `struct_after` store entity state as JSON — never store raw Eloquent model attributes, always map to the Domain Entity first
- `audit_logs` is append-only — no updates, no deletes, ever

## Security

- Passwords: `Hash::make()` with 12+ rounds (bcrypt)
- Sanctum tokens expire after 24 hours: `createToken(..., expiresAt: now()->addHours(24))`
- Rate-limit sensitive endpoints: 5 attempts / 15 min for login
- Never use `unserialize()` on external input; use `json_decode(..., flags: JSON_THROW_ON_ERROR)`
- Log security events with `user_id`, `TenantContext::tenantId`, `ip` and `timestamp`

## OAuth

- Use **Laravel Socialite** for Google and Microsoft OAuth — never implement OAuth flows manually
- The backend uses the stateless driver: `Socialite::driver('google')->stateless()->userFromToken($token)`
- Never store provider OAuth tokens server-side — only store the provider user ID (`google_id`, `microsoft_id`)
- User lookup on OAuth: find by `google_id` / `microsoft_id` first, then by `email` as fallback (account linking)
- On first OAuth login, create the user with `password_hash = null` — OAuth users have no password
- The Socialite adapter lives in `Infrastructure/Gateways/` — never call Socialite from a UseCase directly
