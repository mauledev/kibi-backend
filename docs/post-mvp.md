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
