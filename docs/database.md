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
- `tenants.owner_id BIGINT FK users.id` — the single owner of the tenant. Immutable after creation.
- `users.tenant_id FK tenants.id` is added after `tenants` is created (end of `create_tenants_table` migration) to break the circular FK insertion order.
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
  contact_email varchar(255)
  contact_phone varchar(30)
  status varchar(20) [not null, default: 'active',
    note: 'pending, active, suspended, grace_period, offboarding']
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

### permission_categories
```sql
Table permission_categories {
  id bigserial [pk, increment]
  uuid uuid [unique, not null, default: `gen_random_uuid()`]
  school_id bigint [ref: > schools.id,
    note: 'NULL = system category (Softlinkia), NOT NULL = school category']
  name varchar(100) [not null,
    note: 'academic, financial, hr, communication, configuration']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    school_id
  }
}
```

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
    note: 'NULL = Softlinkia system role']
  name varchar(100) [not null]
  slug varchar(100) [not null,
    note: 'owner, director, teacher, soporte_l1']
  hierarchy_level smallint [not null,
    note: '1=Superadmin, 3=Gestor, 4=Director, 7=Teacher']
  is_system_role boolean [default: false,
    note: 'true = Softlinkia fixed role, permissions managed in code']
  created_at timestamptz [default: `now()`]
  deleted_at timestamptz

  indexes {
    (tenant_id, slug)
    hierarchy_level
    is_system_role
  }
}
```

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
    note: 'NULL = tenant-level role (owner, gestor)']
  assigned_by bigint [ref: > users.id]
  assigned_at timestamptz [default: `now()`]
  revoked_at timestamptz

  indexes {
    (user_id, revoked_at)
    (school_id, role_id)
  }
}
```

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

- **Permission categories** (school_id = null): `academic`, `financial`, `hr`, `communication`, `configuration`
- **System permissions**: 30 slugs including `grade.publish`, `payment.approve`, `manage.permissions`, `role.assign`, `role.view`, `user.suspend`, etc.
- **System roles** (tenant_id = null): Superadmin (L1, is_system_role=true), Owner (L2), Gestor de Escuelas (L3), Director (L4), Coordinador Académico (L5), Control Escolar (L5), Prefectura (L6), Finanzas (L6), RRHH (L6), Docente (L7), Alumno (L8), Tutor (L8)
- **Default role_permissions**: Gestor and Director receive `manage.permissions`. Sensible defaults assigned per role based on their functional scope.

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
