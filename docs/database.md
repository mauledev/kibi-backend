# Database

Database decisions, table schema, relationships and multi-tenancy strategy.

---

## Strategy

Single database with `tenant_id` on every table as the multi-tenancy isolation key.
Subdomains handle routing to the correct tenant context at the HTTP layer.

Every query that touches tenant-owned data must include a tenant scope as the first
filter. No exceptions.

---

## Conventions

- Primary keys: `BIGSERIAL` — internal only, never exposed outside the system
- Public identifiers: `uuid UUID DEFAULT gen_random_uuid()` — used in all endpoints, routes and views
- Soft deletes: `deleted_at TIMESTAMPTZ` on every table that represents a domain entity. Junction/pivot tables do not need soft delete.
- Timestamps: every domain entity table must have both `created_at` and `updated_at` as `TIMESTAMPTZ`. Use `timestampsTz()` in migrations — never `timestamps()` (which generates `TIMESTAMP` without timezone). Exception: `audit_logs` is append-only and only has `created_at`.
- JSON fields: `JSONB` always, never `JSON`
- Naming: `snake_case` for tables and columns, plural for table names

---

## Multi-tenancy rules

- `tenants` is the root table. Every other table that belongs to a tenant has `tenant_id BIGINT NOT NULL FK tenants.id`
- `users.tenant_id` is nullable — `NULL` means Softlinkia staff. During tenant creation the owner user is created first (no `tenant_id` yet), the tenant is created with `owner_id`, then `users.tenant_id` is set in the same transaction.
- `users.is_staff BOOLEAN NOT NULL DEFAULT false` — explicit flag for Softlinkia staff. More readable than relying solely on `tenant_id IS NULL`.
- `tenants.owner_id BIGINT FK users.id` — the single owner of the tenant. Immutable after creation. Answers "who has absolute authority over this tenant?" and powers the Gate bypass (`context.ownerId === user.id → allow everything`). This is intentionally separate from `users.tenant_id` — they answer different questions (see note below).
- `tenants.custom_roles_limit SMALLINT` — maximum number of custom roles the tenant can create in total. Range: 1–50. Set by the owner via `ConfigureCustomRoleLimitUseCase`. NULL means no custom roles have been allowed yet.
- `users.tenant_id FK tenants.id` is added after `tenants` is created (end of `create_tenants_table` migration) to break the circular FK insertion order.
- `roles.category_id FK permission_categories.id` — nullable. Determines which permission category a role is bound to. When set, the role can only hold permissions belonging to that category. NULL means the role is either a custom role (can hold any permission) or a special tenant-admin role (owner, gestor) whose authority is not permission-based.
- Custom roles belong to the tenant (`roles.tenant_id`), not to a specific school. School availability is controlled by `custom_role_schools`. A custom role can be available in one or many schools, but the role entity and its `role_permissions` are tenant-level and consistent across all schools.
- `roles.is_system_role` — `true` exclusively for Softlinkia staff roles (`tenant_id IS NULL`). Never true for tenant roles. Protects staff roles from deletion. Superadmin has `is_system_role = true` with no `category_id` — its authority is handled by the Gate bypass on staff routes. All other staff roles (support, finance) have `is_system_role = true` with a `staff`-scoped `category_id` and manage their permissions via `role_permissions` rows like any other role. Tenant roles — including owner and gestor — always have `is_system_role = false`.
- `roles.hierarchy_level` — present in the schema for future use but **not enforced in business logic**. Role authority is determined by slug-based rules hardcoded in the domain (`owner > gestor > director`). See `post-mvp.md` for the plan to re-activate this column when role creation is extended beyond the current three-actor model.
- `user_role_assignment_denials` — permission subtractions applied to a specific `user_role_assignments` row. Effective permissions for any assignment = role permissions − denials. Does not apply to owner or gestor assignments.

> **Why does the owner user have both `users.tenant_id` and `tenants.owner_id`?**
>
> These two fields are not redundant — they serve different purposes:
> - `tenants.owner_id` answers "who has special immutable authority over this tenant?" It is used exclusively for the Gate bypass and is never used to scope queries.
> - `users.tenant_id` answers "in which tenant can this user authenticate?" It is used to scope every query in `EloquentUserRepository` (`WHERE tenant_id = ?`). Without it set on the owner, the login query would return null and the owner could not log in.
>
> The cost of this design is a circular FK between `users` and `tenants`, which forces a three-step creation in a transaction: insert user (no `tenant_id` yet) → insert tenant (with `owner_id`) → update user's `tenant_id`. The alternative — removing `users.tenant_id` from the owner and making every repository query check both `tenant_id = ?` AND `id = owner_id` — complicates every query in the system without meaningful benefit at this scale.
- `tenants.status = 'pending'` is set when a tenant is created by staff. It transitions to `'active'` when the owner activates their account via the signed URL flow. `TenantMiddleware` blocks all subdomain requests while the tenant is `pending`.
- `users.email_verified_at` is `NULL` for newly created owner users. It is set by `EloquentActivationRepository::activate()` inside the same transaction that sets the password and activates the tenant.
- Tables that operate at school level carry `school_id` only. Reaching `tenant_id` from `school_id` is done via join when needed — no denormalization unless a specific query justifies it
- `audit_logs` and `schools` are the only tables that carry both `tenant_id` and `school_id` explicitly

---

## Schema

### tenants
```sql
Table tenants {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  owner_id bigint [ref: > users.id, note: 'Immutable. The single owner of this tenant.']
  name varchar(255) [not null]
  slug varchar(100) [unique, not null]
  legal_name varchar(255)
  rfc varchar(13)
  fiscal_address jsonb
  contact_name varchar(255)
  contact_email varchar(255)
  contact_phone varchar(30)
  branding jsonb [note: '{ logo_url, primary_color, secondary_color }. Set during onboarding step 2.']
  status varchar(20) [not null, default: 'active',
    note: 'pending, active, suspended, grace_period, offboarding']
  custom_roles_limit smallint [note: '1–50. Set by the owner. Controls the maximum number of custom roles the tenant can create in total. NULL = not configured, custom role creation is blocked.']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz
}
```

### schools
```sql
Table schools {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  tenant_id bigint [not null, ref: > tenants.id]
  name varchar(255) [not null]
  slug varchar(100) [unique]
  address jsonb
  phone varchar(30)
  status varchar(20) [not null, default: 'active']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (tenant_id, status)
  }
}
```

### levels
```sql
Table levels {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  school_id bigint [not null, ref: > schools.id]
  name varchar(100) [not null, note: 'Primaria, Secundaria, Preparatoria']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    school_id
  }
}
```

### grades
```sql
Table grades {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  level_id bigint [not null, ref: > levels.id]
  name varchar(50) [not null, note: '1°, 2°, 3°']
  sequence smallint [not null]
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (level_id, sequence)
  }
}
```

### groups
```sql
Table groups {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  grade_id bigint [not null, ref: > grades.id]
  name varchar(50) [not null, note: 'A, B, C']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    grade_id
  }
}
```

### subjects
```sql
Table subjects {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  level_id bigint [not null, ref: > levels.id]
  name varchar(200) [not null, note: 'Matemáticas I, Física II']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    level_id
  }
}
```

### academic_cycles
```sql
Table academic_cycles {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  school_id bigint [not null, ref: > schools.id]
  name varchar(100) [not null, note: '2025-2026']
  starts_at date [not null]
  ends_at date [not null]
  is_active boolean [default: false]
  created_at timestamptz [default: `now()`]

  indexes {
    (school_id, is_active)
  }
}
```

### users
```sql
Table users {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  tenant_id bigint [ref: > tenants.id, note: 'NULL = Softlinkia staff']
  is_staff boolean [not null, default: false, note: 'true = Softlinkia internal staff']
  email varchar(255) [unique, not null]
  email_verified_at timestamptz [note: 'NULL = account not yet activated']
  password_hash varchar(255)
  google_id varchar(100)
  microsoft_id varchar(100)
  first_name varchar(100) [not null]
  last_name_paternal varchar(100) [not null]
  last_name_maternal varchar(100)
  phone varchar(30)
  status varchar(20) [not null, default: 'active']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (tenant_id, status)
    email
    is_staff
    (last_name_paternal, first_name) [note: 'for alphabetical school lists']
  }
}
```

### student_profiles
```sql
Table student_profiles {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  user_id bigint [unique, not null, ref: > users.id, note: '1:1 with users. One profile per student user.']
  birth_date date
  national_id varchar(50) [note: 'CURP/RUT/CPF/DNI depending on country']
  enrollment_number varchar(50)
  gender varchar(20) [note: 'male, female, other, prefer_not_to_say']
  blood_type varchar(5)
  group_id bigint [ref: > groups.id, note: 'NULL = no group assigned']
  created_at timestamptz [default: `now()`]
  updated_at timestamptz [default: `now()`]
  deleted_at timestamptz
}
```

Student profile data is stored in a dedicated table rather than polluting `users`. Identity fields (name, email, phone) live on `users`; academic fields (birth date, national ID, enrollment number, gender, blood type, group) live here.

The public route identifier for a student is the **user's UUID** (`users.uuid`), not `student_profiles.uuid`. Student endpoints (`GET /students/{uuid}`) always resolve by user UUID to keep the concept of "user" consistent across modules.

### permission_categories
```sql
Table permission_categories {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  scope varchar(20) [not null,
    note: '"staff" | "tenant" | "school". Prevents name collisions across contexts.']
  name varchar(100) [not null]
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (scope, name) [unique]
  }
}
```

Categories are global, pre-seeded by Softlinkia and non-negotiable. The `scope` column separates categories that share the same name across different contexts.

**Rule: one category per system role.** Each operational role is bound to its own category. A teacher cannot receive coordinator permissions — assign a second role instead. Custom roles have no category and can hold permissions from any category.

| scope | name | Role slug |
|---|---|---|
| `staff` | support | _(future: support L1/L2/L3)_ |
| `staff` | finance | _(future: finance L1/L2/L3)_ |
| `tenant` | finance | `tenant_finance` |
| `tenant` | hr | `tenant_hr` |
| `school` | director | `director` |
| `school` | academic_coordinator | `academic_coordinator` |
| `school` | school_registrar | `school_registrar` |
| `school` | prefect | `prefect` |
| `school` | finance | `finance` |
| `school` | hr | `hr` |
| `school` | teacher | `teacher` |
| `school` | student | `student` |
| `school` | tutor | `tutor` |

The same name (e.g. `finance`) can exist in multiple scopes — they are completely independent categories with different permission sets.

### permissions
```sql
Table permissions {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  category_id bigint [not null, ref: > permission_categories.id]
  name varchar(100) [not null]
  slug varchar(100) [unique, not null,
    note: 'grade.publish, payment.approve, manage.permissions']
  created_at timestamptz [default: `now()`]

  indexes {
    category_id
    slug
  }
}
```

### roles
```sql
Table roles {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  tenant_id bigint [ref: > tenants.id,
    note: 'NULL = Softlinkia staff role only']
  category_id bigint [ref: > permission_categories.id,
    note: 'NULL = custom role or special tenant-admin role (owner, gestor). NOT NULL = operational role bound to a category.']
  name varchar(100) [not null]
  slug varchar(100) [not null,
    note: 'owner, gestor, director, teacher, finance, soporte_l1']
  hierarchy_level smallint [not null,
    note: 'Stored but not enforced in business logic. Reserved for future use. See post-mvp.md.']
  is_system_role boolean [default: false,
    note: 'true ONLY for Softlinkia staff roles (tenant_id IS NULL). Never true for tenant roles.']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (tenant_id, slug)
    category_id
    is_system_role
  }
}
```

**Role types:**

| Type | `tenant_id` | `category_id` | `category.scope` | `is_system_role` | Who creates |
|---|---|---|---|---|---|
| Staff — Superadmin | NULL | NULL | — | true | Softlinkia seeder |
| Staff — operational (support, finance) | NULL | category id | `staff` | true | Softlinkia seeder |
| Tenant-admin (owner, gestor) | tenant id | NULL | — | false | Softlinkia seeder |
| Tenant operational (tenant finance, tenant HR…) | tenant id | category id | `tenant` | false | Softlinkia seeder |
| School operational (director, teacher, finance…) | tenant id | category id | `school` | false | Softlinkia seeder |
| Custom role | tenant id | NULL | — | false | Owner or gestor |

Custom roles are identified at runtime by: `tenant_id IS NOT NULL AND category_id IS NULL AND slug NOT IN ('owner', 'gestor')`. The special slugs `owner` and `gestor` are reserved and hardcoded in the domain.

### role_permissions
```sql
Table role_permissions {
  role_id bigint [not null, ref: > roles.id]
  permission_id bigint [not null, ref: > permissions.id]

  indexes {
    (role_id, permission_id) [pk]
  }
}
```

### user_role_assignments
```sql
Table user_role_assignments {
  id bigserial [pk, increment]
  user_id bigint [not null, ref: > users.id]
  role_id bigint [not null, ref: > roles.id]
  school_id bigint [ref: > schools.id,
    note: 'NULL = tenant-level role (owner, gestor). NOT NULL = school-scoped role.']
  assigned_by bigint [ref: > users.id]
  assigned_at timestamptz [default: `now()`]
  revoked_at timestamptz

  indexes {
    (user_id, revoked_at)
    (school_id, role_id)
  }
}
```

A user can hold the same role in multiple schools — each school produces a separate row. Effective permissions per school = role permissions − denials recorded in `user_role_assignment_denials` for that specific row.

### custom_role_schools
```sql
Table custom_role_schools {
  role_id    bigint [not null, ref: > roles.id]
  school_id  bigint [not null, ref: > schools.id]

  indexes {
    (role_id, school_id) [pk]
  }
}
```

Defines in which schools a custom role is available for assignment. Only applies to custom roles (`category_id IS NULL`, slug not in reserved list). Created at the same time the custom role is created — owner and gestor select the schools during creation. A custom role with no rows here cannot be assigned to any user.

System roles and operational roles do not use this table — their availability is tenant-wide by default.

### user_role_assignment_denials
```sql
Table user_role_assignment_denials {
  id bigserial [pk, increment]
  role_user_assignment_id bigint [not null, ref: > user_role_assignments.id]
  permission_id bigint [not null, ref: > permissions.id]

  indexes {
    (role_user_assignment_id, permission_id) [unique]
  }
}
```

Subtracts specific permissions from a single assignment without modifying the role itself. This allows the same role to behave differently per user per school.

Rules:
- Cannot be applied to owner or gestor assignments.
- The denied permission must belong to the role's `category_id` (or any category if the role is custom).
- Managed by `DenyPermissionFromAssignmentUseCase` and `RestorePermissionToAssignmentUseCase`.

### teacher_subject_groups
```sql
Table teacher_subject_groups {
  id bigserial [pk, increment]
  user_id bigint [not null, ref: > users.id]
  subject_id bigint [not null, ref: > subjects.id]
  group_id bigint [not null, ref: > groups.id]
  assigned_at timestamptz [not null, default: `now()`]
  unassigned_at timestamptz

  indexes {
    (user_id, unassigned_at)
    (subject_id, group_id) [unique, note: 'WHERE unassigned_at IS NULL']
  }
}
```

### onboarding_progress
```sql
Table onboarding_progress {
  id bigserial [pk, increment]
  uuid uuid [unique, not null]
  tenant_id bigint [unique, not null, ref: > tenants.id, note: '1:1 with tenant. cascadeOnDelete.']
  current_step smallint [not null, default: 1]
  status varchar(20) [not null, default: 'in_progress', note: 'in_progress | completed. "suspended" is a read-time computation, never persisted.']
  grace_period_ends_at timestamptz [not null]
  created_at timestamptz
  updated_at timestamptz
}
```

The `suspended` status is a read-time computation on the Domain entity (`getEffectiveStatus()`) — when `grace_period_ends_at` has passed and status is still `in_progress`. It is never stored.

### onboarding_step_status
```sql
Table onboarding_step_status {
  progress_id bigint [not null, ref: > onboarding_progress.id, note: 'cascadeOnDelete']
  step smallint [not null, note: '1, 2, or 3']
  name varchar(40) [not null, note: 'company-data | branding | create-school']
  status varchar(20) [not null, default: 'pending', note: 'pending | in_progress | completed | skipped']
  completed_at timestamptz [null]

  indexes {
    (progress_id, step) [pk]
  }
}
```

No `timestamps` columns — this is a pivot-like table tracking step state within a progress record.

### tutor_profiles
```sql
Table tutor_profiles {
  id bigserial [pk, increment]
  uuid uuid [unique, not null]
  user_id bigint [unique, not null, ref: > users.id]
  occupation varchar(100) [null]
  created_at timestamptz [default: `now()`]
  updated_at timestamptz
  deleted_at timestamptz
}
```

One profile per tutor user. `user_id` is unique — a user can only have one tutor profile. Tutor identity (name, email, phone) is stored in `users` and joined in queries. Business logic lives in `App\Modules\Tutor\Domain\Entities\Tutor`.

### student_tutors
```sql
Table student_tutors {
  id bigserial [pk, increment]
  tutor_user_id bigint [not null, ref: > users.id]
  student_user_id bigint [not null, ref: > users.id]
  relationship varchar(50) [null, note: 'mother | father | guardian | other']
  linked_at timestamptz [not null, default: `now()`]
  unlinked_at timestamptz [null]

  indexes {
    (tutor_user_id, student_user_id) [unique, note: 'WHERE unlinked_at IS NULL — partial index via DB::statement in migration']
    student_user_id
  }
}
```

Junction table linking tutors to students. References `users.id` on both sides — kept decoupled from `tutor_profiles` and `student_profiles` to avoid cross-module FK dependencies. A link is active when `unlinked_at IS NULL`. The partial unique index prevents duplicate active links between the same tutor+student pair. No soft deletes — rows are logically deactivated by setting `unlinked_at`.

Magic link behaviour: when the first active link for a student is created (`hasActiveLink(studentUserId)` returns false before insert) and the student's email is unverified (`email_verified_at IS NULL`), a magic link is sent. Subsequent tutors linking to the same student do not resend.

### audit_logs
```sql
Table audit_logs {
  id bigserial [pk, increment]
  school_id bigint [ref: > schools.id,
    note: 'NULL = tenant-level action']
  user_id bigint [ref: > users.id]
  action varchar(100) [not null,
    note: 'Convention: {model}.{verb} — payment.approve, grade.publish']
  entity_id bigint
  struct_before jsonb
  struct_after jsonb
  created_at timestamptz [default: `now()`]

  indexes {
    (school_id, created_at)
    (user_id, created_at)
    (action, entity_id)
  }
}
```

---

## Seeded system data

`RolesAndPermissionsSeeder` (runs always, before `DevSeeder`) inserts:

- **Permission categories** — seeded with three scopes:
  - `staff` scope: `support`, `finance` (for Softlinkia staff operational roles)
  - `tenant` scope: `finance`, `hr` (for tenant-level operational roles, e.g. tenant billing manager)
  - `school` scope: `director`, `academic_coordinator`, `school_registrar`, `prefect`, `finance`, `hr`, `teacher`, `student`, `tutor`
- **System permissions**: seeded per category. Each permission belongs to exactly one category. Administrative permissions (e.g. `user.create`, `user.suspend`, `permissions.manage`, `roles.custom.create`) are seeded alongside the first modules that need them.
- **Staff roles** (`tenant_id = NULL`, `is_system_role = true`):
  - Superadmin — no `category_id`, authority via Gate bypass on staff routes.
  - Support L1, L2, L3 — `category_id` pointing to `staff/support`.
  - Finance L1, L2, L3 — `category_id` pointing to `staff/finance`.
- **Tenant-admin roles** (`is_system_role = false`, `category_id = NULL`): Owner (Gate bypass), School Manager (all permissions in assigned schools, authority by slug). Neither holds `role_permissions` rows — their authority is entirely Gate-driven.
- **Tenant operational roles** (`is_system_role = false`, `category_id` of scope `tenant`): Tenant Finance (`tenant_finance`), Tenant HR (`tenant_hr`).
- **School operational roles** (`is_system_role = false`, `category_id` of scope `school`): Director, Academic Coordinator, School Registrar, Prefect, Finance, HR, Teacher, Student, Tutor. Each role has its own exclusive category — cross-category permissions require assigning a second role.
- **Default role_permissions**: each operational role (staff, tenant, or school) receives the permissions that belong to its category. These are the defaults — authorized actors can adjust them within category bounds.

Re-running the seeder is safe — all inserts use `insertOrIgnore`.

Re-running the seeder is safe — all inserts use `insertOrIgnore`.

---

## Audit log action convention

Actions follow the pattern `{model}.{verb}`:

```
-- CRUD verbs
payment.create        grade_record.create
payment.update        grade_record.update
payment.delete        grade_record.delete

-- Domain verbs
payment.approve       grade_record.publish
payment.reject        user.suspend
subscription.cancel   nfc_card.deactivate
role.assign           role.revoke
permission.grant      permission.revoke
```

Querying by model:

```sql
-- All actions on a model
WHERE action LIKE 'payment.%'

-- Specific action
WHERE action = 'payment.approve'

-- All domain actions (non-CRUD)
WHERE action NOT LIKE '%.create'
  AND action NOT LIKE '%.update'
  AND action NOT LIKE '%.delete'

-- History of a specific record
WHERE action LIKE 'payment.%'
  AND entity_id = 1453
```
