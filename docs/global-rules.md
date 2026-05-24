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
| Role slugs | `snake_case` — `gestor_escuelas`, `control_escolar` |
| Public IDs in routes | `{public_id}` — never `{id}` |

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

## Multi-tenancy

- Every repository method that queries tenant-owned data must scope by `tenant_id` as the **first** filter — a query without this scope is a bug, not a shortcut
- `TenantContext` is injected into repositories via constructor — never resolve the tenant from inside a UseCase or Repository directly
- Never expose `id` (BIGSERIAL) in any response or route — always use `public_id` (UUID)

## Roles and permissions

- Owner bypasses all permission checks via `Gate::before` — never add manual owner checks inside UseCases, that is the Gate's responsibility
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
- Log security events with `user_id`, `tenant_id`, `ip` and `timestamp`

## OAuth

- Use **Laravel Socialite** for Google and Microsoft OAuth — never implement OAuth flows manually
- The backend uses the stateless driver: `Socialite::driver('google')->stateless()->userFromToken($token)`
- Never store provider OAuth tokens server-side — only store the provider user ID (`google_id`, `microsoft_id`)
- User lookup on OAuth: find by `google_id` / `microsoft_id` first, then by `email` as fallback (account linking)
- On first OAuth login, create the user with `password_hash = null` — OAuth users have no password
- The Socialite adapter lives in `Infrastructure/Gateways/` — never call Socialite from a UseCase directly
