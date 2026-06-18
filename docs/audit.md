# Audit

How KIBI records an immutable trail of critical actions for LFPDPPP compliance.

---

## Where it lives

| Piece | Path |
|---|---|
| Writer contract | `app/Common/Audit/AuditLoggerInterface` |
| Writer (append-only) | `app/Common/Audit/AuditLogger` |
| Event catalog | `app/Common/Audit/Events/` |
| Storage | `audit_logs` table (append-only) |

Audit logging is a cross-cutting concern, so it lives in `app/Common/` and not inside any single module.

---

## Event catalog (registry)

Every auditable action is a case of a string-backed enum implementing `AuditEvent`, one enum per module under `app/Common/Audit/Events/`. `AuditEventRegistry` aggregates them all.

```php
use App\Common\Audit\Events\AuthAuditEvent;

$this->audit->log(AuthAuditEvent::LOGIN, userId: $user->getId());
```

`log()` accepts an `AuditEvent` (preferred — type-checked and discoverable) or a raw string (backward compatible).

### Naming convention

`{model}.{verb}` — two lowercase `snake_case` segments, exactly one dot. Same convention documented in `docs/global-rules.md` and `docs/database.md` (which also defines the `WHERE action LIKE 'model.%'` query pattern — keep the model segment aligned with that vocabulary). Examples: `auth.login`, `school.create`, `charge.create`, `grade_record.capture`. Outcomes that matter are encoded in the verb (`auth.login` vs `auth.login_failed`).

> Enforced by `tests/Unit/Common/Audit/AuditEventRegistryTest`: every catalog value must match the convention, be globally unique, and each critical module must define a minimum number of events.

> **Provisional scope.** Today only the Roles UseCases emit audit events in production. The other module enums (Treasury, Payments, Academic, Dunning, Hardware, Students, Impersonation, Reports) are declared up-front so the LFPDPPP catalog exists from day one, but their exact case names are **provisional** and will be refined when each module is built (Treasury lands in #16; Hardware/NFC has not started). Don't treat them as a frozen contract yet.

---

## Tenant & school attribution

Every `audit_logs` row carries `tenant_id` and `school_id` so the trail can be segmented per institution. **The writer derives both from the request-scoped container context — callers do not pass them by hand.**

- `AuditLogger::log()` falls back to `TenantContext` and `SchoolContext` (bound per-request by `TenantMiddleware` / `SchoolMiddleware`) whenever the caller omits `tenantId` / `schoolId`.
- An explicit argument always wins over the derived context. Pass one only when the audited entity belongs to a tenant/school other than the current request context (e.g. a value already loaded from the affected row).
- On **staff routes** no `TenantContext` is bound, so `tenant_id` stays `null` — a **controlled null**: staff/superadmin actions (`superadmin.*`, `staff.access_denied`) are not tenant-scoped by design. Likewise `school_id` is `null` on tenant-level requests that send no `X-School-Uuid` header.

This centralization means a new UseCase emitting an event automatically attributes it to the right institution without any extra wiring — there is nothing to forget.

> Enforced by `tests/Feature/Common/Audit/AuditLoggerTest` (the *tenant/school attribution from request context* group).

---

## Struct payloads: identify entities by UUID

`struct_before` / `struct_after` are `jsonb` snapshots of the affected state. **They identify entities by their public `uuid`, never by internal numeric ids** — the same rule the API follows (`docs/api.md`). The audit trail is append-only and feeds a future reports module, so the snapshot must stay readable even after the entity is deleted and must expose only public identifiers.

Two keys, one rule:

| Key | For | Example |
|---|---|---|
| `uuid` | The **affected entity** — the one `entity_id` points to | `payment.reject` → `{"uuid": "<payment-uuid>", "status": "rejected"}` |
| `{relation}_uuid` | Any **other referenced entity** | `role.assign` → `{"uuid": "<assignment-uuid>", "assigned_user_uuid": "…", "role_uuid": "…"}` |

- The affected entity is **always** `uuid` — whether or not other entities are present. This keeps the access pattern uniform: the reports module reads `struct->>'uuid'` to resolve the audited entity for *every* event. Every **other** referenced entity is named with `{relation}_uuid` (e.g. `permission.deny` → `{"uuid": "<assignment-uuid>", "permission_uuid": "…"}`).
- `entity_id` (internal id, indexed) stays as the canonical pointer in its own column — it is *not* part of the struct. The struct carries the public `uuid` of that same entity plus non-identity state fields (status, name, `deleted_at`, reason, amounts, …).
- Do **not** put internal ids (`id`, `tenant_id`, `*_id`) inside structs. Replace them with the `uuid` equivalent.
- The reports module reads `struct_after->>'uuid'` (or `->>'{relation}_uuid'`) to resolve entities — no join to internal ids.

**Controlled exceptions** (left without a struct uuid on purpose):
- Session/security events with no domain entity: `auth.login`, `auth.login_failed`, `auth.logout`, `auth.oauth_login`, `policy.accepted` (the actor is already in the `user_id` column).
- Scalar-config or step events whose "entity" is not a domain object: `tenant.custom_roles_limit.update`, `onboarding.step_completed`.

When a struct needs to reference an actor that arrives as a raw int (not loaded as an entity), pass their `uuid` down through the UseCase input from the controller's authenticated user rather than storing the id — e.g. `superadmin.create` carries both dual-control signatures as `proposed_by_uuid` and `approved_by_uuid`.

---

## What IS audited

Any action that mutates a domain entity or carries compliance/security weight:

- **Auth** — login, failed login, OAuth login, logout, password reset, account activation
- **Tenant / Schools** — create, suspend/deactivate, reactivate, branding change
- **Roles** — role create/update/delete, role assign/revoke, permission grant/revoke
- **Academic** — grade capture/update, term close, report card generation, attendance
- **Treasury** — charge create/update, reconciliation, refund
- **Payments (Mercado Pago)** — checkout outcomes (initiate, approve, reject)
- **Dunning** — reminder dispatch, escalation, service suspension
- **Hardware** — device register/deactivate, biometric enroll, access denial
- **Students** — enrollment, deactivation, reactivation
- **Impersonation (E-22)** — every action performed while impersonating
- **Reports** — export, generation

## What is NOT audited

- Routine read-only queries and UI list/detail views (sensitive-data exports are audited via `report.export`)
- Routine per-user notification deliveries (only bulk dispatch + config changes)
- Chat message content (only moderation: flag, archive)
- Health checks and framework-internal events

---

## Immutability & retention

- `audit_logs` is **append-only** — never `UPDATE` or `DELETE`. The writer only inserts; a DB-level guard is tracked in the parent epic (E-12).
- Retention: **5 years** (LFPDPPP). Partitioning and cold storage are out of scope of the catalog and tracked separately in E-12.

---

## Adding a new event

1. Find the module enum in `app/Common/Audit/Events/` (or create `<Module>AuditEvent.php` implementing `AuditEvent`).
2. Add a `case NAME = 'model.verb';` following the convention.
3. If you created a new enum, register it in `AuditEventRegistry::modules()`.
4. Emit it from the mutation UseCase: `$this->audit->log(<Module>AuditEvent::NAME, userId: …, entityId: …, structBefore: …, structAfter: …)`. Do **not** pass `tenantId` / `schoolId` — they are derived from the request context automatically (see *Tenant & school attribution*); pass them only to override with a different institution. Identify entities in the structs by `uuid` / `{relation}_uuid`, never by internal id (see *Struct payloads: identify entities by UUID*).
5. `composer quality` and the registry test must stay green.
