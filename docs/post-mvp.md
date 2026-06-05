# Post-MVP

Architectural decisions consciously deferred for the mature version of the system.
Each entry describes the current behavior, the problem it creates at scale, and the recommended solution.

These are not bugs — they are known trade-offs accepted for MVP.

---

## PM-001 — School-scoped permissions

**Current behavior**

`User::activePermissions()` merges permission slugs from **all active role assignments**, regardless of which school the assignment belongs to. A Director assigned to School A with `teachers.create` effectively holds that permission across all schools in the tenant.

Security at the data layer is preserved because every repository method filters by `school_id` — a Director can never read or mutate records outside their assigned school. But the permission check itself is not school-aware.

**Problem at scale**

A single tenant running 10 schools where some staff work across multiple schools in different roles (e.g., Director of School A, Teacher in School B) will produce incorrect UI gates on the frontend. The user will see buttons and actions they should not see in the context of the school they are currently operating in.

**Recommended solution**

Introduce `SchoolContext` — a per-request value object bound by a middleware or resolved from the active route — analogous to `TenantContext`:

```php
final class SchoolContext
{
    public function __construct(public readonly int $schoolId) {}
}
```

Extend `hasPermissionTo` and `activePermissions` to accept an optional school scope:

```php
public function activePermissions(?int $schoolId = null): array
// When $schoolId is provided, only roles where
// user_role_assignments.school_id = $schoolId OR school_id IS NULL
// (tenant-level roles like Owner, Gestor) are included.
```

The gate would pass `SchoolContext` automatically so controllers and use cases remain unchanged:

```php
Gate::after(function (User $user, string $ability) use ($container): ?bool {
    $schoolId = $container->bound(SchoolContext::class)
        ? $container->make(SchoolContext::class)->schoolId
        : null;
    return $user->hasPermissionTo($ability, $schoolId) ? true : null;
});
```

**Why deferred**

For MVP, all tenants are expected to operate one or a small number of schools with no cross-school staff overlap. Repository-level scoping prevents data leakage. The UI inconsistency is cosmetic at this stage.

Revisit before onboarding tenants with more than 3 schools or any staff member holding roles at multiple schools simultaneously.

---

## PM-003 — Superadmin cross-tenant access (impersonation)

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

## PM-002 — Teacher / Tutor role incompatibility

**Context**

The system supports multi-role — a user can hold several role assignments simultaneously. However, one combination is explicitly forbidden: a user cannot hold both `docente` (Teacher) and `tutor` (Tutor/Parent) roles at the same school.

The conflict arises when a teacher has a child enrolled at the school where they work. As a teacher they have operational access to all students in their assigned groups (grades, attendance, etc.). As a tutor they should only have visibility into their own child's data. Allowing both roles simultaneously creates an access ambiguity that the current permission model cannot resolve cleanly — the merged permission set would grant teacher-level access disguised as parental access, or vice versa.

**Current behavior (MVP)**

No validation exists in `AssignRoleToUserUseCase` to prevent this combination. The constraint is not enforced. For MVP this is acceptable because initial tenants are not expected to have teachers who are also parents at the same school.

**Recommended solution**

Introduce an `incompatible_roles` concept — either a config table or a hardcoded map in the Domain layer:

```php
// Domain/Contracts/RoleCompatibilityInterface.php
interface RoleCompatibilityInterface
{
    /** @return array<string> slugs that cannot coexist with $roleSlug for the same user+school */
    public function incompatibleWith(string $roleSlug): array;
}

// Initial map:
// 'docente' => ['tutor']
// 'tutor'   => ['docente']
```

`AssignRoleToUserUseCase` checks this before persisting the assignment and throws a domain exception if a conflict is found. The check is school-scoped — a user can be a teacher at School A and a tutor at School B without conflict.

**Why deferred**

The rule requires knowing which roles a user already holds at a given school before assigning a new one, plus maintaining the incompatibility map as the role catalog grows. For MVP the edge case is not present in the target user base.

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
