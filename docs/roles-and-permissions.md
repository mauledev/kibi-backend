# Roles and Permissions — Team Guide

This document explains how the roles and permissions system works, who can do what, and why it is designed this way. It is intended for engineers joining the project who need to understand the model before implementing or extending it.

---

## Core concepts

### 1. A user can hold multiple roles

A user is not locked into a single role. A person can be a Director in School A and a Teacher in School B. Each combination of user + role + school is a row in `user_role_assignments`.

### 2. Roles are scoped to a school

Most roles apply within a specific school (`school_id` in the assignment). Exceptions are `owner` and `school_manager`, whose assignments have `school_id = NULL` because they operate across schools within the tenant.

### 3. Roles are bound to a permission category

Every operational role has a `category_id`. Categories have a `scope` that separates contexts that would otherwise share a name:

| scope | Meaning |
|---|---|
| `staff` | Softlinkia internal roles (support L1/L2/L3, finance) |
| `tenant` | Tenant-level operational roles (tenant finance manager, tenant HR) |
| `school` | School-level operational roles (director, teacher, school finance) |

This means `staff/finance` and `school/finance` are completely independent categories with different permission sets. A school finance user cannot receive Softlinkia staff finance permissions, and vice versa.

Practical consequence:
- A school director role can only hold permissions from the `school/director` category.
- A school teacher role can only hold permissions from the `school/teacher` category.
- You cannot give a teacher role finance permissions. If a person needs finance permissions, they need the finance role.

Custom roles have no `category_id` and can hold permissions from any category and scope. They are created at the **tenant level** — not per school — and their school availability is defined at creation time. The same custom role can be available in multiple schools, and its permissions are identical across all of them.

### 4. Effective permissions = role permissions − denials

The same role can behave differently for different users in different schools. A `user_role_assignment_denials` row subtracts a specific permission from a specific assignment, without touching the role itself.

Example: two users both have the `finance` role in the same school. One of them should not be able to delete invoices. Rather than creating a second role, a denial is added to that user's assignment for `invoices.delete`.

### 5. School context matters

The `X-School-Uuid` request header tells the backend which school the user is currently operating in. The permission gate uses this to:
- Consider only the assignments for that school.
- Apply only the denials for those assignments.

A user may have `invoices.delete` in School B but not in School A — the header determines which set of permissions is active.

---

## Role types

| Type | `tenant_id` | `category.scope` | `is_system_role` | Created by |
|---|---|---|---|---|
| Staff — Superadmin | NULL | — (no category) | true | Softlinkia seeder |
| Staff — operational (support, finance) | NULL | `staff` | true | Softlinkia seeder |
| Tenant-admin (owner, school_manager) | tenant id | — (no category) | false | Softlinkia seeder |
| Tenant operational (tenant finance, tenant HR…) | tenant id | `tenant` | false | Softlinkia seeder |
| School operational (director, teacher, school finance…) | tenant id | `school` | false | Softlinkia seeder |
| Custom | tenant id | — (no category) | false | Owner or school_manager |

Custom roles are identified at runtime by: `tenant_id IS NOT NULL AND category_id IS NULL AND slug NOT IN ('owner', 'school_manager')`.

`is_system_role = true` marks Softlinkia staff roles only — it protects them from deletion. It does not imply that permissions are managed in code: Superadmin has no category and uses a Gate bypass, while support and finance staff roles use `role_permissions` like any other operational role.

`is_system_role = true` is reserved exclusively for Softlinkia staff roles (`tenant_id IS NULL`). Tenant roles — including owner and school_manager — always have `is_system_role = false`.

---

## Who can do what

### Creating users

| Actor | Can create users with these roles |
|---|---|
| Owner | Any role in the tenant |
| Gestor | Any role scoped to their assigned schools |
| Director | Any role scoped to their school |
| Everyone else | Cannot create users |

### Assigning roles

Same actors as above. Additionally:
- The `owner` slug cannot be assigned via `AssignRoleToUserUseCase` — ownership is immutable and set only at tenant creation.
- Mutual exclusions are enforced: teacher ↔ student, teacher ↔ tutor, student ↔ tutor cannot coexist for the same user in the same school.

### Managing permissions on a role

| Actor | Scope |
|---|---|
| Owner | Any role |
| Gestor | Roles assigned within their schools |
| Director | Roles of users in their school. Cannot touch school_manager or owner roles. |

The permission must belong to the role's category. Custom roles accept permissions from any category.

### Creating custom roles

Only **owner** and **school_manager** can create custom roles. The tenant must have `custom_roles_limit` configured by the owner (1–50).

Custom roles live at the **tenant level** — they are not owned by any single school. At creation time, the creator selects which schools the role will be available in. This creates rows in `custom_role_schools`. The role's permissions are the same regardless of which of those schools it is used in — there is no per-school permission variation at the role level (only per-user denials can create differences).

### Configuring custom role limit

Only the **owner** can set `tenants.custom_roles_limit`.

### Denying permissions on an assignment

Owner, school_manager (in their schools), and director (in their school) can add or remove denials. Denials cannot be applied to owner or school_manager assignments.

---

## School access control

Gestores and directors can only operate within schools they have an active assignment for. Every UseCase that performs a school-level action verifies this before proceeding. The owner skips this check — they have access to all schools in their tenant.

---

## Worked example

This example walks through a realistic setup from tenant creation to granular permission management.

### Setup

**Staff creates the tenant and owner.**
Softlinkia staff calls `POST /staff/tenants` with the tenant name, slug, and owner details. The owner receives an activation email.

**Owner activates and logs in.**
The owner sets their password via the signed URL and accesses their dashboard.

**Owner creates three schools.**
```
School AA, School BB, School CC
```

**Owner configures the custom role limit.**
```
PUT /tenant/custom-roles-limit  { "limit": 5 }
```
The tenant can now have up to 5 custom roles in total.

**Owner creates two school_manageres.**
```
User: Admin11 → role: school_manager, schools: [School AA, School CC]
User: Admin22 → role: school_manager, schools: [School BB]
```

Each school_manager has two separate rows in `user_role_assignments` (one per school). Both have `school_id` set — school_manageres are always school-scoped in their assignments, even though they have no `category_id` restriction on their permissions.

---

### Admin11 sets up School AA

Admin11 sends `X-School-Uuid: <School AA uuid>` with every request in this section.

**Creates a director.**
```
POST /users  { name: "Director AA", role: "director" }
```
Director AA gets an assignment: director role + School AA.

**Creates two teachers.**
```
POST /users  { name: "Teacher AA", role: "teacher" }
POST /users  { name: "Teacher BB", role: "teacher" }
```

**Adds Teacher AA to School CC as well.**
Admin11 also manages School CC, so they switch the header to `X-School-Uuid: <School CC uuid>` and call:
```
POST /users/<teacher-aa-uuid>/roles  { role: "teacher", school_uuid: "<School CC>" }
```
Teacher AA now has two active assignments: teacher in School AA and teacher in School CC. Their permissions in each school are evaluated independently.

---

### Admin22 sets up School BB

Admin22 sends `X-School-Uuid: <School BB uuid>`.

**Creates a finance user.**
```
POST /users  { name: "Finance AA", role: "finance" }
```
Finance AA has one assignment: finance role + School BB.

---

### Admin11 adds Finance AA to School AA

Admin11 switches to `X-School-Uuid: <School AA uuid>` and assigns the finance role to Finance AA there:
```
POST /users/<finance-aa-uuid>/roles  { role: "finance", school_uuid: "<School AA>" }
```

Finance AA now has two assignments:
- finance role, School BB (created by Admin22) — full finance permissions
- finance role, School AA (created by Admin11) — full finance permissions for now

---

### Director AA gets finance permissions for School AA

The director role only has director-category permissions. Director AA needs to view and approve invoices too. Admin11 assigns them the finance role as well:

```
POST /users/<director-aa-uuid>/roles  { role: "finance", school_uuid: "<School AA>" }
```

Director AA now has two active assignments in School AA:
- director role → director permissions
- finance role → finance permissions

The Gate merges both sets. Director AA can do everything a director can do AND everything a finance role can do.

**But Director AA should not be able to create invoices — only view and approve them.**

Admin11 adds a denial on Director AA's finance assignment:
```
POST /users/<director-aa-uuid>/assignments/<finance-assignment-uuid>/denials
{ "permission_uuid": "<invoices.create uuid>" }
```

Now Director AA's effective finance permissions = finance role permissions − `invoices.create`. The finance role itself is unchanged — Finance AA is not affected.

---

### Finance AA gets different permissions per school

Finance AA was confirmed by Admin22 to have full finance permissions in School BB. But in School AA they should not be able to delete invoices.

Admin11 (who manages School AA) adds a denial on Finance AA's School AA assignment:
```
POST /users/<finance-aa-uuid>/assignments/<school-aa-finance-assignment-uuid>/denials
{ "permission_uuid": "<invoices.delete uuid>" }
```

**Final state for Finance AA:**

| School | Assignment | Denials | Can delete invoices? |
|---|---|---|---|
| School BB | finance role | none | Yes |
| School AA | finance role | `invoices.delete` | No |

The same role, the same user, different effective permissions per school.

---

## Key rules to remember

1. **Permissions come from roles.** You cannot grant a permission directly to a user — you assign them a role that carries the permission.

2. **Denials only subtract.** You cannot grant a permission that the role does not already have. Denials only remove permissions the role provides.

3. **Category bounds prevent permission creep.** You cannot add a finance permission to a teacher role. Use the finance role instead.

4. **Custom roles are the escape hatch.** When a role needs permissions that span multiple categories, create a custom role. They have no category restriction.

5. **The owner is above the permission system.** The owner's access is determined by `tenants.owner_id`, not by role assignments or permission checks. The Gate bypasses all checks for the owner.

6. **Superadmin is above the permission system on staff routes.** Any staff user with `is_system_role = true` on their active role gets full access via `Gate::before`. The bypass is active only when `StaffContext` is bound (staff routes). `is_system_role` is the runtime signal — not the slug.

7. **Gestores have all permissions in their schools.** Their authority comes from their slug, not from `role_permissions` rows.

7. **School context is always required for school-level operations.** Send `X-School-Uuid` on every request that operates within a school. Absent header = tenant-level context only.

---

## Database tables involved

| Table | Purpose |
|---|---|
| `roles` | Role definitions. `category_id` bounds permissions. `is_system_role` marks Softlinkia staff roles. |
| `permission_categories` | Global categories pre-seeded by Softlinkia. `scope` (`staff`/`tenant`/`school`) prevents name collisions across contexts. |
| `permissions` | Individual permission slugs, each belonging to one category. |
| `role_permissions` | Which permissions a role holds. Subject to category bounds. Tenant-level — same for all schools. |
| `custom_role_schools` | Which schools a custom role is available in. Created alongside the custom role. |
| `user_role_assignments` | Which role a user holds in which school. One row per user+role+school. |
| `user_role_assignment_denials` | Permissions subtracted from a specific assignment. Per-user, per-school exceptions. |
| `tenants.custom_roles_limit` | Maximum number of custom roles the tenant can create. Set by owner. |
