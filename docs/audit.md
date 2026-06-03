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

---

## What IS audited

Any action that mutates a domain entity or carries compliance/security weight:

- **Auth** — login, failed login, OAuth login, logout, password reset, account activation
- **Tenant / Schools** — create, suspend/deactivate, reactivate, branding change
- **Roles** — role create/update/delete, role assign/revoke, permission grant/revoke
- **Academic** — grade capture/update, term close, report card generation, attendance
- **Treasury / Payments** — charge create/update, payment reconcile, refund, checkout outcomes
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
4. Emit it from the mutation UseCase: `$this->audit->log(<Module>AuditEvent::NAME, userId: …, entityId: …, structBefore: …, structAfter: …)`.
5. `composer quality` and the registry test must stay green.
