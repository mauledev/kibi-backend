# Post-MVP

Architectural decisions consciously deferred for the mature version of the system.
Each entry describes the current behavior, the problem it creates at scale, and the recommended solution.

These are not bugs — they are known trade-offs accepted for MVP.

---


## PM-003 — hierarchy_level re-activation

**Current behavior (MVP)**

The `hierarchy_level` column exists in the `roles` table but is not used in business logic. Role authority is determined by slug-based rules hardcoded in the domain (`owner > gestor > director`). Only these three roles can create users or assign roles to others.

**Problem at scale**

If user creation or role assignment is ever extended beyond the three hardcoded actors (e.g., a coordinator who can register students, or a custom role that can onboard tutors), the hardcoded rules cannot accommodate it without code changes.

**Recommended solution**

Re-activate `hierarchy_level` as the runtime authority mechanism. `AssignRoleToUserUseCase` would check `actor.lowestHierarchyLevel() < target_role.hierarchyLevel` instead of checking the actor's slug against a hardcoded list. The column is already populated by the seeder, so no data migration is needed — only business logic changes.

**Why deferred**

For MVP the three-actor model (owner, gestor, director) covers all user creation scenarios. Extending it introduces complexity in determining which roles a given actor can assign, which is better solved once the full role catalog is stable.

---

## PM-004 — Superadmin cross-tenant access (impersonation)

**Current behavior**

Superadmin holds a `Gate::before` bypass identical to Owner, granting full access to all `/staff` routes. However, Superadmin cannot access tenant environments. Staff users have `is_staff = true` — the tenant repositories scope by owner match and role assignments, so a staff user authenticating on `{tenant_slug}.kibi.com` would not be found and could not log in.

**Problem at scale**

The Softlinkia support team needs to access tenant environments to debug issues, assist with onboarding, and inspect data. Without a cross-tenant mechanism, they must rely on indirect methods (DB queries, impersonating tenant accounts) which are insecure and unaudited.

**Recommended solution**

An **impersonation** feature on staff routes:

```
POST /staff/tenants/{uuid}/impersonate
```

This endpoint (Superadmin only) generates a short-lived Sanctum token scoped to the target tenant with a special `impersonated_by` claim. All audit log entries written during an impersonation session record both the real actor (`staff_user_id`) and the tenant context. The session expires after a fixed window (e.g., 2 hours) and cannot be renewed.

**Why deferred**

Impersonation requires audit trail design, session expiry enforcement, and careful permission scoping to prevent privilege escalation. The feature is non-trivial and not needed for initial tenant onboarding.

---

## PM-005 — Guest As: staff temporary access to tenant environments

> **Note:** This solution is open to discussion. The design below is a starting point, not a final decision.

**Current behavior (MVP)**

Staff users (`is_staff = true`, `tenant_id = NULL`) have no mechanism to access tenant environments. A staff user who sends a valid token to a tenant route passes `auth:sanctum` but gets 403 on every permission-guarded endpoint because they hold no roles in any tenant. There is no explicit middleware rejection at the tenant boundary — protection depends entirely on `authorize()` calls being present in every controller.

**Problem at scale**

The Softlinkia support team needs to enter tenant environments to reproduce bugs reported by specific users. Indirect methods (raw DB queries, asking the tenant owner to share credentials) are insecure, unaudited, and slow.

**Proposed solution**

A **Guest As** session system, separate from the impersonation approach in PM-004:

```
POST /staff/tenants/{uuid}/debug-sessions
DELETE /staff/tenants/{uuid}/debug-sessions/{session_uuid}
```

A new `staff_tenant_sessions` table tracks active sessions:

```sql
Table staff_tenant_sessions {
  id              bigint [pk, increment]
  staff_user_id   bigint [not null, ref: > users.id]
  tenant_id       bigint [not null, ref: > tenants.id]
  role_id         bigint [nullable, ref: > roles.id]   -- null = owner-level read-only
  school_id       bigint [nullable, ref: > schools.id] -- required when role is school-scoped
  expires_at      timestamptz [not null]               -- default: now + 24h
  revoked_at      timestamptz [nullable]
  created_by      bigint [not null, ref: > users.id]
  indexes {
    (staff_user_id, tenant_id)
  }
}
```

A new `StaffDebugSessionMiddleware` sits inside the tenant middleware group. When a staff user's token is detected on a tenant route, the middleware looks up an active (non-expired, non-revoked) session for that `(staff_user_id, tenant_id)` pair. If found, it marks the request with the session context so the Gate can act accordingly.

**Role selection**

The staff member chooses the role to assume based on the bug being debugged:

- **Specific role** (e.g. `director` at Prepa Central) — reproduces exactly what the affected user sees. The `role_id` and `school_id` columns hold this assignment; no actual row is created in `user_role_assignments`.
- **No role (`role_id = null`)** — grants owner-level read access (GET requests only) for inspecting system configuration. All mutations remain blocked.

This distinction means staff can both reproduce user-facing bugs (specific role) and inspect tenant configuration (owner-level read) without polluting the tenant's user or role assignment tables.

**Audit trail**

All requests made during a Guest As session must record `staff_user_id` as the actor in `audit_logs`, alongside the assumed `tenant_id` and `role_id`. This preserves a clear trace of what was accessed and who authorized the session.

**Why deferred**

Requires designing the session lifecycle (creation, expiry enforcement, manual revocation), the Gate integration for role spoofing without real DB assignments, and the audit log schema changes. Not needed for initial tenant onboarding.

---

## PM-006 — Per-request context objects (TenantContext, SchoolContext) via Service Locator

**Current behavior (MVP)**

`TenantContext` and `SchoolContext` are bound to the IoC container as per-request instances inside their respective middleware:

```php
app()->instance(TenantContext::class, new TenantContext(...));
app()->instance(SchoolContext::class, new SchoolContext(...));
```

Controllers and use cases that need these objects resolve them via `app(TenantContext::class)` or `app(SchoolContext::class)` — the Service Locator pattern. The dependency is hidden inside the method body instead of being declared in the signature.

`SchoolContext` is additionally optional: `SchoolMiddleware` only binds it when the `X-School-Uuid` header is present. This makes constructor or method injection unsafe — attempting to inject it when it is not bound throws `BindingResolutionException`.

**Problem at scale**

- Dependencies are invisible at the call site, making controllers harder to read and test without bootstrapping the full container.
- Any code path that forgets to check `app()->bound(SchoolContext::class)` before calling `app(SchoolContext::class)` will throw at runtime.
- Unit tests must manually call `app()->instance(...)` to set up context instead of passing it as a constructor argument.

**Recommended solution**

Move per-request context out of the IoC container and into **request attributes**, set by the middleware:

```php
// TenantMiddleware / SchoolMiddleware
$request->attributes->set('tenant_context', new TenantContext(...));
$request->attributes->set('school_context', new SchoolContext(...));  // optional
```

Controllers receive the context through the already-injected `$request` object:

```php
$tenantContext = $request->attributes->get('tenant_context');
$schoolContext = $request->attributes->get('school_context'); // null if header absent
```

This makes optionality explicit, removes the `app()` calls, and keeps context scoped to the HTTP layer where it belongs. Use cases in the Application layer receive only scalar IDs (as they do today), so the change is contained to middleware and controllers.

**Why deferred**

Requires touching both middleware classes and every controller that consumes a context object. The current approach works correctly for MVP and the risk of the refactor outweighs the benefit at this stage.

---

## PM-002 — Role mutual exclusions — table-driven enforcement

**Current behavior (MVP)**

Mutual exclusions between roles are enforced in `AssignRoleToUserUseCase` via a hardcoded enum in the domain:

- `teacher` and `student` cannot coexist for the same user in the same school.
- `teacher` and `tutor` cannot coexist for the same user in the same school.
- `student` and `tutor` cannot coexist for the same user in the same school.

The check is school-scoped — a user can be a teacher at School A and a tutor at School B without conflict.

**Problem at scale**

As the role catalog grows, hardcoded enums require code changes and redeployment to add or modify exclusion rules. Tenants with unusual role structures cannot define their own exclusion policies.

**Recommended solution**

Introduce a `role_exclusions` table:

```sql
Table role_exclusions {
  role_id            bigint [not null, ref: > roles.id]
  excluded_role_id   bigint [not null, ref: > roles.id]
  indexes {
    (role_id, excluded_role_id) [pk]
  }
}
```

`AssignRoleToUserUseCase` queries this table instead of reading the hardcoded enum. The seeder populates the initial pairs. Tenant-level custom exclusions can be added without code changes.

**Why deferred**

The hardcoded enum covers all known incompatible combinations for MVP tenants. A table adds migration and seeder complexity with no immediate benefit.

---

## PM-004 — Treasury workflow scope cuts

**Current behavior (MVP)**

The Treasury module ships with the minimal workflow that section §12.3 / §12.12 of `KIBI_Requerimientos_v6.pdf` describes for the 12-week MVP:

- A single Superadmin (`users.is_staff = true`) handles every payment validation. There is no Líder de Tesorería / Operador separation.
- Two state transitions out of `pending` are exposed: `approve` and `reject`. Both targets are terminal from the operator's perspective.
- Routes live under `/api/staff/treasury/*` and the repository operates cross-tenant (`EloquentPaymentRepository` does not inject `TenantContext`).
- Authorization is enforced via `is_staff = true` in the controller (no granular `payment.view` / `payment.approve` / `payment.reject` slugs — staff system roles do not use the dynamic permission table).
- Audit logging covers approve and reject only; the event-based notification system is not wired.

**Explicitly out of scope for MVP**

The following pieces of the requirement set (mostly RF-160..189i) are deferred:

| Item | RF reference | Notes |
|---|---|---|
| Líder / Operador role separation + manual ticket assignment (push model) | RF-160..167 | Single Superadmin in MVP |
| `request-evidence` flow → `with_observation` state | (frontend spec §5.1.7) | Enum case exists but the transition has no UseCase or endpoint |
| Remittance batch generation (`payments → remesado`) | (ticket §Backend) | `remittances` and `payment_concepts` tables not created |
| Mercado Pago webhook + idempotency | RF-PAY-01, 02 | Owner uploads receipts manually in MVP |
| CFDI stamping via PAC (`stamp-cfdi`, `cancel-cfdi`) | RF-182..189i | Done manually by an external accountant in MVP |
| Tutor receipt PDF generation | §12.12 | Owner-issued PDFs only, not generated by KIBI |
| Owner-side payment receipt upload + download via signed URLs | (req doc §11.2) | Speculative download infra was removed from MVP; will be redesigned alongside the upload feature in a single ticket |
| 5 Domain Events + 3 Listeners (PaymentCreated/Approved/Rejected/EvidenceRequested/RemittanceCreated) | (ticket) | The audit log covers MVP traceability |
| Granular permission slugs (`treasury.view` / `treasury.approve` / `treasury.reject`) | (ticket §Esc. 6) | `is_staff` gate suffices in MVP |
| Owner-side endpoint to upload receipts | (req doc §11.2) | The Owner upload entrypoint is its own feature ticket |
| Auto-suspension after 5 days past due | §12.12 | Lives in the Tenant module, not Treasury |

**Why deferred**

The ticket "Tesorería · workflow BE + bandeja Operador FE" was written against the full product vision and not pre-trimmed against the MVP cuts documented in §12.3 / §12.12 of the requirements PDF. Implementing the full workflow would add ~40-60h on top of the MVP scope (remittances + evidence + MP webhook + 5 events + 3 listeners + virtualised frontend table) and is unnecessary for the 12-week milestone.

**Recommended solution**

When picking up the full workflow post-MVP:

1. Introduce a separate `EloquentPaymentRepository` variant scoped to the operator's assigned tickets, bound contextually for the Operador role.
2. Add `remittances` and `payment_concepts` tables + state event `Remitted`.
3. Wire Domain Events (`PaymentApproved`, etc.) and queued Listeners for notifications (`Tutor` channel via email + in-app).
4. Implement the Mercado Pago webhook controller with idempotency via `webhook_logs` (request_id deduplication).
5. Split the `is_staff` gate into granular permission slugs once Softlinkia roles need fine-grained delegation (e.g. Líder cannot timbrar CFDI, Operador can).
6. The CFDI piece (RF-182..189i) belongs in a dedicated Facturación module that consumes Treasury's approved payments rather than living inside Treasury itself.
