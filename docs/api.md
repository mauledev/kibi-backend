# API

Endpoint conventions, responses, authentication and error handling.

---

## ApiResponse

All JSON responses go through `app/Http/Response/ApiResponse`. Never use `response()->json()` directly.

```php
ApiResponse::success($data, $message)         // 200
ApiResponse::created($data)                   // 201
ApiResponse::paginated($data, $pagination)    // 200 — adds pagination object to meta
ApiResponse::error($message, $status)         // generic error
ApiResponse::notFound()                       // 404
ApiResponse::unauthorized()                  // 401
ApiResponse::forbidden()                     // 403
ApiResponse::conflict($message, $errors)     // 409
```

`paginated()` places a `pagination` key inside `meta` alongside the standard `timestamp`, `path`, and `request_id` fields. The `$pagination` array must contain `total`, `per_page`, `current_page`, and `last_page`.

Every response envelope:

```json
{
    "success": true,
    "status": 200,
    "message": "Operation successful",
    "data": {},
    "errors": null,
    "meta": {
        "timestamp": "2024-01-01T00:00:00+00:00",
        "path": "/api/auth/login",
        "request_id": "req_abc123"
    }
}
```

---

## Authentication

Authentication uses Laravel Sanctum with token-based auth. Token lifetime: 24 hours.

Every authenticated request must include:
```
Authorization: Bearer {token}
```

### Domain separation

| Domain | Audience | Tenant resolution |
|---|---|---|
| `app.kibi.com` | Softlinkia staff | None — `is_staff = true` |
| `{tenant_slug}.kibi.com` | School users | Resolved from subdomain slug |

Staff routes do not go through `TenantMiddleware`. School routes always do.

### Tenant resolution flow

```
{tenant_slug}.kibi.com
  → TenantMiddleware resolves Tenant by slug
  → binds TenantContext{tenantId} into container
  → user authenticated within that tenant
```

---

## Request headers

### X-Active-Role

When a user holds multiple roles, the frontend sends the currently selected role:

```
X-Active-Role: {role_uuid}
```

**UI context only.** The frontend uses it to decide which dashboard to render. The backend never reads it — permission checks are always evaluated against all active assignments for the current school.

### X-School-Uuid

Identifies the school the user is currently operating in:

```
X-School-Uuid: {school_uuid}
```

Read by `SchoolMiddleware`, which resolves the UUID to a `school_id`, verifies it belongs to the current tenant, and binds `SchoolContext` into the container. The Gate uses this context to scope permission checks to the correct `user_role_assignments` rows and apply the corresponding `user_role_assignment_denials`.

Required on all school-level endpoints. Absent on tenant-level endpoints (e.g. managing gestores, configuring custom role limits).

---

## Authorization

Three bypass mechanisms exist, evaluated in order. All are registered in `AppServiceProvider::registerGates()`.

### 1. Owner bypass — tenant routes (`Gate::before`)

The user whose `id` matches `TenantContext::ownerId` is granted every ability unconditionally. Identity comes from `tenants.owner_id`, not from a role assignment. Only active on routes where `TenantMiddleware` has bound `TenantContext`.

### 2. Superadmin bypass — staff routes (`Gate::before`)

Any staff user (`is_staff = true`) holding at least one role with `is_system_role = true` is granted every ability unconditionally. Only active on routes where `StaffMiddleware` has bound `StaffContext`.

### 3. Gestor bypass — school level (`Gate::after`)

A user holding an active `school_manager` assignment for the current school (`X-School-Uuid`) is granted every ability within that school's scope. Evaluated in `Gate::after`, so it fires only when the two `Gate::before` bypasses did not match.

### Everyone else — dynamic permission check

Permission checks use the slug convention:

```php
$this->authorize('grade.publish');
$this->authorize('payment.approve');
$this->authorize('manage.permissions');
```

`Gate::after` loads the union of all active permission slugs from `user_role_assignments` scoped to the current school and checks whether the requested ability is present.

### Hierarchy enforcement

Any endpoint that assigns a role or grants a permission must verify that the acting user's `hierarchy_level` is strictly lower than the target role's `hierarchy_level`. This check lives in the UseCase, not the Controller.

---

## Public identifiers

Internal `id` (BIGSERIAL) is never exposed in any endpoint. This rule applies in both directions:

- **Responses and route parameters** always use `uuid`. Internal `id` is never serialized nor returned.
- **Requests (body and query string)** always accept `uuid`. FormRequests never validate an `*_id` field received from an external client.

```
GET /schools/{uuid}
GET /users/{uuid}
GET /roles/{uuid}
```

**JSON response key**: the identifier field in every Resource must be named `uuid`, never `id`.

```php
// Correct
'uuid' => $user->uuid,

// Wrong — never expose 'id'
'id' => $user->uuid,
```

When a UseCase or repository requires an integer `id` (e.g. for FK writes or joins), the Controller is responsible for resolving the UUID to the internal `id` before constructing the Input DTO:

```php
// Controller — resolve UUID → id before entering Application layer
$school = School::where('uuid', $request->validated('school_uuid'))->value('id');

new AssignRoleToUserInput(schoolId: $school);
```

The Application and Domain layers only ever see integer ids for internal references; they never perform UUID lookups themselves.

---

## Route structure

Routes are split into three groups in `routes/api.php`:

```
/staff/*         — Staff routes (app.kibi.com). No tenant middleware.
/auth/*          — Auth flows (both domains). No auth:sanctum on login.
/tenant/*        — All tenant resources ({tenant_slug}.kibi.com).
```

```php
// Staff routes — no tenant middleware
Route::prefix('staff')->group(function () { ... });

// Public — no tenant middleware, no auth
Route::get('/auth/tenant-info', ...);
Route::post('/auth/activate', ...);

// Tenant domain
Route::middleware('tenant')->group(function () {
    // Auth flows (no prefix)
    Route::post('/auth/login', ...);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', ...);
        Route::post('/auth/logout', ...);

        // All tenant resources under /tenant prefix
        Route::prefix('tenant')->group(function () {
            // schools, roles, permissions, users, ...
        });
    });
});
```

The three top-level segments cleanly mirror the three user domains: `staff`, `auth`, and `tenant`.

---

## Postman collection

The project ships a Postman collection (`kibi-api.postman_collection.json`) that is **not versioned** (excluded via `.gitignore`). It must be kept in sync manually.

**Rule:** every time an endpoint is created, removed, or has its path, method, body, or response shape changed, update the Postman collection to reflect those changes before closing the task.

---

## Endpoints

### Auth

```
POST   /auth/login               Authenticate with email + password, returns token
POST   /auth/logout              Revoke current token
POST   /auth/oauth/{provider}    OAuth login/register (provider: google | microsoft)
POST   /auth/activate            Activate owner account via signed URL (public, no tenant middleware)
```

`POST /auth/oauth/{provider}` accepts `{ "access_token": "..." }` and returns the same `LoginOutput` as a password login. See `docs/oauth.md` for the full flow.

`POST /auth/activate` is a public endpoint (no `auth:sanctum`, no `TenantMiddleware`). It expects the signed URL query params (`user`, `expires`, `signature`) forwarded from the frontend SPA, plus a JSON body `{ "password": "...", "password_confirmation": "..." }`. On success it returns the same `LoginOutput` as a standard login, plus a 24h Sanctum token.

### Staff

```
POST   /staff/auth/login                                    Authenticate Softlinkia staff
GET    /staff/auth/me                                       Return authenticated staff user data
POST   /staff/auth/logout                                   Revoke staff token
GET    /staff/tenants                                       List all tenants (paginated) with embedded owners
POST   /staff/tenants                                       Create a new tenant + owner
GET    /staff/tenants/{uuid}                                Get a single tenant with embedded owner
PUT    /staff/tenants/{uuid}                                Update a tenant's name, slug, and status
DELETE /staff/tenants/{uuid}                                Soft-delete a tenant
GET    /staff/roles                                         List all staff roles with their permissions
GET    /staff/roles/{uuid}                                  Get a single staff role with its permissions
POST   /staff/roles/{uuid}/permissions                      Assign a permission to a staff role
DELETE /staff/roles/{uuid}/permissions/{permission_uuid}    Revoke a permission from a staff role
```

`GET /staff/tenants` returns 200 with a paginated list of tenants. Accepts a `page` query parameter (default: 1, page size: 20). Each item includes a compact owner shape (`uuid`, `email`, `full_name`) and a `created_at` ISO 8601 timestamp. The response envelope includes a `meta.pagination` object with `total`, `per_page`, `current_page`, and `last_page`.

`POST /staff/tenants` creates the tenant with `status = 'pending'`, creates the owner user with no password, assigns the `owner` role, and sends an activation email with a 48h signed URL. Returns 201 with the tenant and embedded owner. Returns 409 when the slug or email is already taken.

`GET /staff/tenants/{uuid}` returns 200 with the full tenant and embedded owner. Returns 404 when not found. The owner shape includes `uuid`, `email`, `first_name`, `last_name_paternal`, `last_name_maternal`, and `full_name`.

`PUT /staff/tenants/{uuid}` accepts `{ "name": "...", "slug": "...", "status": "..." }`. Valid status values: `pending`, `active`, `suspended`. Returns 200 with the updated tenant. Returns 404 when not found. Returns 409 when the new slug is already taken by another tenant.

`DELETE /staff/tenants/{uuid}` soft-deletes the tenant. Returns 200 with a success message. Returns 404 when not found.

### Schools

```
GET    /tenant/schools                          List schools of the current tenant
POST   /tenant/schools                          Create a school
GET    /tenant/schools/{uuid}                   Get a single school
PUT    /tenant/schools/{uuid}                   Update mutable fields (partial)
POST   /tenant/schools/{uuid}/deactivate        Soft-delete a school
```

`GET /tenant/schools` accepts an optional `?status=` query param to narrow the result set:

| Value          | Meaning                                                                                |
|----------------|----------------------------------------------------------------------------------------|
| *(omitted)*    | Equivalent to `active` — the default. There is no separate "no-filter" mode.           |
| `active`       | `status='active'` AND not soft-deleted.                                                |
| `deactivated`  | Only soft-deleted rows.                                                                |
| `all`          | Every row, including soft-deleted and any rows with a non-`active` status (suspended). |

This is the canonical pattern for list endpoints that need to expose soft-deleted rows alongside lifecycle states. The filter values are a Domain enum (`SchoolListFilter`); the FormRequest validates via `Rule::enum(...)` and the controller never inlines strings. The repository contract accepts a **Criteria** object (`SchoolListCriteria`) rather than loose primitives, so adding pagination, search or sorting later does not break callers. The Criteria's `status` field is non-nullable with `Active` as its default — "include everything" is expressed exclusively as `All`, eliminating the previous `null`/`All` ambiguity. The repository maps `Deactivated` to `onlyTrashed()` and `All` to `withTrashed()`; the soft-delete column stays an Infrastructure concern. Resources expose `deleted_at` so the frontend can render deactivated rows distinctly.

### Users

```
GET    /tenant/users                   List users in the current tenant (paginated, filterable)
GET    /tenant/users/{uuid}            Get a single user with full detail
POST   /tenant/users                   Invite a tenant user (creates a pending account + sends a magic link)
PUT    /tenant/users/{uuid}            Update a user (not yet implemented — returns 501)
DELETE /tenant/users/{uuid}            Delete a user (not yet implemented — returns 501)
```

`GET /tenant/users` returns 200 with a paginated list of tenant users. Authorization requires the `user.view` permission. Accepts optional query parameters:

| Parameter | Type | Description |
|---|---|---|
| `q` | string (max 255) | Free-text search across `first_name`, `last_name_paternal`, `last_name_maternal`, `email` (Postgres ILIKE, case-insensitive) |
| `filter[role]` | string or string[] | Filter by role slug(s). Users must hold an active assignment with at least one of the provided slugs. The reserved value `none` (sent alone) inverts the filter and returns only users with **no active role assignment** — see below. |
| `filter[status]` | string | Filter by lifecycle status. Allowed: `active`, `inactive`, `suspended`. |
| `page` | integer (min 1) | Page number. Default: 1. |
| `per_page` | integer (1–100) | Items per page. Default: 20. |

**Unassigned users (`filter[role]=none`).** Sending the reserved sentinel `none` as the sole value of `filter[role]` returns only users that have **no active role assignment** (every assignment revoked, or never assigned). This is the inverse of the normal include filter and is mutually exclusive with concrete slugs — when `none` is present, any other slug in the array is ignored. The school scope does not apply in this mode (a role-less user belongs to no school); only tenant + `is_staff = false` scoping holds. Use case: surfacing newly created staff who still need a role/school assigned.

**School visibility is authority-driven, not header-driven.** The set of schools a caller can list is derived on the server from the actor, so omitting `X-School-Uuid` can never widen visibility beyond what the actor is entitled to:

| Actor | Without `X-School-Uuid` | With `X-School-Uuid` |
|---|---|---|
| Owner (`tenants.owner_id`) | All users in the tenant (every school) | Narrowed to that one school |
| Non-owner (gestor, director, …) | Union of all schools where they hold an active assignment | That school, only if it is within their accessible set — otherwise `403` |

A non-owner who requests a school they have no active assignment in receives `403` (`SchoolAccessDeniedException`). A non-owner with no school-scoped assignments (tenant-level role only) sees an empty list unless they are the owner. The `X-School-Uuid` header therefore only ever *narrows* the scope an actor already has.

Response shape:
```json
{
  "success": true,
  "status": 200,
  "data": [
    {
      "uuid": "...",
      "full_name": "Mauricio Ledesma García",
      "email": "mauricio@example.com",
      "phone": "+52 55 1234 5678",
      "status": "active",
      "roles": [
        { "role_uuid": "...", "slug": "teacher", "name": "Teacher", "school_uuid": "..." }
      ],
      "created_at": "2025-01-15T10:00:00+00:00"
    }
  ],
  "meta": {
    "pagination": {
      "total": 42,
      "per_page": 20,
      "current_page": 1,
      "last_page": 3
    }
  }
}
```

`GET /tenant/users/{uuid}` returns 200 with the full user detail. Authorization requires `user.view`. Returns 404 when the UUID does not exist within the current tenant.

Detail response includes the list fields plus: `first_name`, `last_name_paternal`, `last_name_maternal`.

Permission slug used: `user.view` (seeded under `school/director` category — also held by `school_registrar`, `prefect`, `finance`, `hr`, and `academic_coordinator` via their respective category slugs).

`POST /tenant/users` **invites** a tenant user. Authorization requires `user.create` (owner bypasses). Body:

```json
{
  "email": "nuevo@colegio.mx",
  "first_name": "Ana",
  "last_name_paternal": "García",
  "last_name_maternal": "López",
  "assignments": [
    { "role_uuid": "...", "school_uuid": "..." }
  ]
}
```

It creates a **pending** user (`password_hash = null`, `email_verified_at = null`) in the current tenant, applies each `assignments[]` entry via the same logic as `POST /users/{uuid}/roles` (hierarchy, role-exclusion and owner-role protections enforced — only `owner`, `school_manager`, `director` may assign; `school_uuid` may be null for tenant-level roles), and emails a signed activation **magic link** to `/auth/magic` (same `auth.activate` signed route + 7-day TTL as owner activation). The invitee sets a password via `POST /auth/activate` and is logged in. Returns 201 with `{ uuid, email, full_name }`. Returns 409 when the email already exists; 403 on a hierarchy/role-exclusion violation; 422 on validation errors. No password is accepted at invite time.

Activation note: `POST /auth/activate` promotes a tenant from `pending → active` only — invited members land on an already-active tenant, so their activation only sets their password and verifies their email (it never re-activates a suspended tenant).

---

### Students

```
GET    /students               List students in the current tenant (paginated, filterable)
GET    /students/{uuid}        Get a single student by user UUID
POST   /students               Create a student (requires X-School-Uuid header)
PUT    /students/{uuid}        Update a student's identity and profile fields
```

`{uuid}` in all student routes is the **user's UUID**, not the `student_profiles.uuid`.

`GET /students` returns 200 with a paginated list of students in the current tenant. Authorization requires `user.view`. Accepts optional query parameters:

| Parameter | Type | Description |
|---|---|---|
| `q` | string | Free-text search across `first_name`, `last_name_paternal`, `email` (Postgres ILIKE) |
| `page` | integer (min 1) | Page number. Default: 1. |
| `per_page` | integer (1–100) | Items per page. Default: 20. |

School visibility is authority-driven (same pattern as `GET /users`):

| Actor | Without `X-School-Uuid` | With `X-School-Uuid` |
|---|---|---|
| Owner | All students in the tenant | Narrowed to that one school |
| Non-owner | Union of all schools where they hold an active assignment | That school, only if within their accessible set |

`GET /students/{uuid}` returns 200 with the full student detail. Returns 404 when the UUID does not exist within the current tenant. Authorization requires `user.view`.

`POST /students` creates a student. **Requires `X-School-Uuid` header** — a student must belong to a school at creation. Authorization requires `user.create`. Body:

```json
{
  "email": "ana.garcia@example.com",
  "first_name": "Ana",
  "last_name_paternal": "García",
  "last_name_maternal": "López",
  "phone": "+52 55 1234 5678",
  "birth_date": "2010-03-15",
  "national_id": "GARL100315MDFXXX01",
  "enrollment_number": "EN-001",
  "gender": "female",
  "blood_type": "O+",
  "group_uuid": "..."
}
```

Creates a `pending` user (no password), assigns the `student` role in the given school, and creates the `student_profiles` row. Returns 201 with the full student detail. Returns 409 when the email is already taken. Returns 403 on a hierarchy or role-exclusion violation. Returns 422 when `X-School-Uuid` is missing or unresolvable.

`PUT /students/{uuid}` updates a student. All fields are optional — only provided (non-null) fields are updated. Both `users` (identity fields) and `student_profiles` (profile fields) may be updated in a single transaction. Returns 200 with the updated student detail. Returns 404 when not found. Authorization requires `user.update`.

Detail response shape:

```json
{
  "uuid": "...",
  "email": "ana.garcia@example.com",
  "first_name": "Ana",
  "last_name_paternal": "García",
  "last_name_maternal": "López",
  "phone": "+52 55 1234 5678",
  "status": "pending",
  "birth_date": "2010-03-15",
  "national_id": "GARL100315MDFXXX01",
  "enrollment_number": "EN-001",
  "gender": "female",
  "blood_type": "O+",
  "group_uuid": "...",
  "group_name": "3° A",
  "created_at": "2026-06-11T00:00:00+00:00"
}
```

---

### Me

```
GET    /tenant/me/onboarding          Onboarding progress of the authenticated user
GET    /tenant/me/schools             Schools the authenticated user can operate in
```

`GET /me/schools` returns the schools the authenticated user can operate in, consumed by the client `SchoolGate` to decide the pre-dashboard flow (no schools / one school / pick a school). Access is **strictly role-based**: a school is returned **only if the user holds an active role assignment in it** (`User::accessibleSchoolIds()` — active `user_role_assignments` with a non-null `school_id`). No role in a school = no access. A user with no school-scoped assignment gets `[]` (this includes the owner, whose tenant-wide access is handled by the client gate's owner short-circuit, not by this endpoint). Compact shape:

```json
[ { "id": "school-uuid", "slug": "primaria-centro", "name": "Escuela Primaria Central", "logo_url": null } ]
```

`id` is the school **uuid**. The percentage/onboarding endpoint above and this one are read-only.


`GET /me/onboarding` returns the invited member's registration progress as a **percentage derived on the fly** from their existing data — required fields filled vs. missing. **Nothing is stored** (no table). Response:

```json
{ "percent": 75, "completed": ["first_name", "last_name_paternal", "email"], "missing": ["phone"], "is_complete": false }
```

The required set lives in `ComputeOnboardingProgressUseCase::requiredFields()` (MVP: minimum profile — `first_name`, `last_name_paternal`, `email`, `phone`) and is meant to grow per role as each role's data points are defined. The percentage rises automatically as the user fills those fields through the relevant resource endpoints; there is no separate "save step" endpoint.

---

### Roles and permissions

```
GET    /tenant/roles                                                          List all roles for current tenant (system + custom)
POST   /tenant/roles                                                          Create a custom role
POST   /tenant/roles/custom                                                   Alias — same as POST /tenant/roles
GET    /tenant/roles/{uuid}                                                   Get role with its permissions
PUT    /tenant/roles/{uuid}                                                   Update role name (custom roles only)
DELETE /tenant/roles/{uuid}                                                   Delete role (custom roles only)
GET    /tenant/permissions                                                     List permissions (filtered by role category if role_uuid provided)
POST   /tenant/roles/{uuid}/permissions                                       Assign permission to role (category-bound)
DELETE /tenant/roles/{uuid}/permissions/{permission_uuid}                     Revoke permission from role
POST   /tenant/users/{uuid}/roles                                             Assign role to user (owner, gestor, director only)
DELETE /tenant/users/{uuid}/roles/{role_uuid}                                 Revoke role from user
POST   /tenant/users/{uuid}/assignments/{assignment_uuid}/denials             Deny a permission for a specific assignment
DELETE /tenant/users/{uuid}/assignments/{assignment_uuid}/denials/{perm_uuid} Restore a denied permission
PUT    /tenant/custom-roles-limit                                             Configure max custom roles (owner only)
```

### Roles and permissions — School scope

```
GET    /tenant/schools/{uuid}/roles                                           List roles available in this school
POST   /tenant/schools/{uuid}/roles                                           Create a custom role scoped to this school
GET    /tenant/schools/{uuid}/roles/{role_uuid}                               Get a role with its permissions
PUT    /tenant/schools/{uuid}/roles/{role_uuid}                               Update role name (custom roles only; system roles cannot be updated)
GET    /tenant/schools/{uuid}/permissions                                     List permissions available for this school
POST   /tenant/schools/{uuid}/roles/{role_uuid}/permissions                   Assign permission to a role
DELETE /tenant/schools/{uuid}/roles/{role_uuid}/permissions/{permission_uuid} Revoke permission from a role
```

**No DELETE for school-scoped roles.** Roles belong to the tenant, not to a specific school. To delete a custom role use `DELETE /tenant/roles/{uuid}` (tenant scope). System roles (director, teacher, etc.) can never be deleted.

`POST /tenant/roles` and `POST /tenant/schools/{uuid}/roles` both create custom roles. The tenant-scope version requires `school_uuids` in the body. The school-scope version infers the school from the URL — body only needs `name` (and optional `slug`).

`GET /tenant/permissions` accepts an optional `?role_uuid=` query param. When provided, returns only permissions belonging to that role's category. When absent (or for custom roles), returns all permissions grouped by category.

### Role shape — `bypasses_permissions` and `granted` flag

Every role endpoint (list and detail) includes `bypasses_permissions: bool`.

| Value | Meaning |
|---|---|
| `true` | Role has unrestricted access via a server-side bypass. The `permissions` array may be empty and should be ignored. |
| `false` | Role operates exclusively by explicit grants. `permissions` is the source of truth. |

Roles with `bypasses_permissions: true`: `superadmin` (staff), `owner` (tenant), `school_manager` (school gestor). These mirror the Gate bypasses registered in `AppServiceProvider`.

```json
{
  "uuid": "...",
  "name": "Superadministrador",
  "slug": "superadmin",
  "is_system_role": true,
  "bypasses_permissions": true,
  "permissions": []
}
```

The detail endpoints (`GET .../roles/{uuid}`) additionally return ALL permissions applicable to the role's scope, each with a `granted` boolean. The list endpoints (`GET .../roles`) return only granted permissions, without the flag.

```json
{
  "uuid": "...",
  "name": "Director",
  "bypasses_permissions": false,
  "permissions": [
    { "uuid": "...", "slug": "students.view",   "name": "Ver alumnos",   "granted": true  },
    { "uuid": "...", "slug": "students.create", "name": "Crear alumnos", "granted": false }
  ]
}
```

**Scope rules for "applicable permissions":**
- Role with `category_id` set → all permissions in that category.
- Custom role (`category_id = null`) → all permissions in the system.

This applies identically to the three detail endpoints: `GET /staff/roles/{uuid}`, `GET /tenant/roles/{uuid}`, and `GET /tenant/schools/{uuid}/roles/{role_uuid}`.

`POST /tenant/users/{uuid}/assignments/{assignment_uuid}/denials` subtracts a permission from a specific `user_role_assignments` row. Cannot be applied to owner or gestor assignments. Body: `{ "permission_uuid": "..." }`.

`PUT /tenant/custom-roles-limit` sets `tenants.custom_roles_limit` for the authenticated owner's tenant. Body: `{ "limit": 10 }` (1–50).

---

### Onboarding

```
GET    /tenant/onboarding/progress               Return current onboarding progress (auto-bootstraps)
POST   /tenant/onboarding/steps/company          Complete step 1 — company data
POST   /tenant/onboarding/steps/branding         Complete step 2 — branding
POST   /tenant/onboarding/steps/first-school     Complete step 3 — link first school
POST   /tenant/onboarding/steps/skip             501 — not implemented (MVP stub)
POST   /tenant/onboarding/steps/academic-template 501 — not implemented (MVP stub)
POST   /tenant/onboarding/steps/fiscal           501 — not implemented (MVP stub)
POST   /tenant/onboarding/steps/payment          501 — not implemented (MVP stub)
POST   /tenant/onboarding/steps/director         501 — not implemented (MVP stub)
```

All onboarding endpoints require `auth:sanctum` + `tenant` middleware. Owner-only access is enforced **inline in the controller** via a private `denyIfNotOwner(Request)` helper that compares `request.user.id` against `TenantContext::ownerId` and returns `403 Forbidden` (`Only the owner can perform onboarding`) when they differ. The check runs at the top of every action and is not extracted to a dedicated middleware because the rule has a single consumer (this controller) and the wizard is a transient flow — extracting it would add indirection without removing repetition elsewhere. If a second owner-only group appears later, promote the helper to `EnsureUserIsTenantOwner` middleware then.

**Wizard closure:** once `progress.status = 'completed'`, the three `POST /onboarding/steps/*` endpoints return `409 Conflict` and reject any write. Post-wizard edits to fiscal data, branding, or the first-school link must go through their respective Settings endpoints (not implemented in MVP — tracked as post-MVP work).

`GET /tenant/onboarding/progress` auto-bootstraps a missing record (handles legacy tenants). Returns an `OnboardingProgressResource` with `uuid`, `current_step`, `status` (effective — may be `suspended` when grace period expired), `steps[]`, `grace_period_ends_at`, `is_grace_period_expired`, `can_access_full_panel`, `created_at`, `updated_at`.

`POST /tenant/onboarding/steps/company` body: `business_name`, `rfc` (auto-uppercased), `fiscal_address` (nested object), `primary_contact_name`, `primary_contact_email`, `primary_contact_phone`. Returns 200 with updated progress. Idempotent.

`POST /tenant/onboarding/steps/branding` body: `logo_url`, `primary_color` (hex), `secondary_color` (hex). Requires step 1 completed (422 if not). Returns 200 with updated progress. Idempotent.

`POST /tenant/onboarding/steps/first-school` body: `school_id` (school UUID). Requires step 2 completed (422 if not). Returns 403 if school UUID does not belong to the tenant. Returns 200 with updated progress. Idempotent.

`BootstrapOnboardingUseCase` is called inside the `CreateTenantUseCase` transaction after tenant creation, so every new tenant gets an onboarding record immediately.

---

### Tutors

```
GET    /tutors                                     List tutors (paginated, filterable)
POST   /tutors                                     Create a tutor (requires X-School-Uuid)
GET    /tutors/{uuid}                              Get a single tutor
PUT    /tutors/{uuid}                              Update tutor fields (partial)
POST   /tutors/{tutorUuid}/students/{studentUuid}  Link tutor to a student
```

All tutor endpoints require `auth:sanctum` + `tenant` middleware.

`GET /tutors` requires the `X-School-Uuid` header and the `user.view` permission. Accepts optional query parameters:

| Parameter | Type | Description |
|---|---|---|
| `q` | string (max 255) | Free-text search across `first_name`, `last_name_paternal`, `email` |
| `page` | integer (min 1) | Page number. Default: 1. |
| `per_page` | integer (1–100) | Items per page. Default: 20. |

School visibility follows the same authority-driven pattern as users: owner sees the whole tenant, non-owners see only schools where they hold an active assignment.

List response shape (each item):
```json
{
  "uuid": "user-uuid",
  "full_name": "María González Pérez",
  "email": "tutor@example.com",
  "phone": "+52 55 1234 5678",
  "status": "active",
  "occupation": "Contadora",
  "created_at": "2025-01-15T10:00:00+00:00"
}
```

`POST /tutors` requires `X-School-Uuid` and the `user.create` permission. Creates a pending user, assigns the `tutor` role in the given school, creates the tutor profile, and sends a magic link activation email to the tutor. Body:

```json
{
  "email": "tutor@example.com",
  "first_name": "María",
  "last_name_paternal": "González",
  "last_name_maternal": "Pérez",
  "phone": "+52 55 1234 5678",
  "occupation": "Contadora"
}
```

Returns 201 with the tutor detail. Returns 409 when the email is already registered. Returns 403 on a hierarchy or role-exclusion violation. Returns 422 when `X-School-Uuid` is missing.

`GET /tutors/{uuid}` requires `user.view`. The `{uuid}` is the user's UUID. Returns 200 with the full tutor detail including `first_name`, `last_name_paternal`, `last_name_maternal`. Returns 404 when not found.

`PUT /tutors/{uuid}` requires `user.update`. All fields are optional — only provided fields are updated:

```json
{
  "first_name": "María",
  "last_name_paternal": "González",
  "last_name_maternal": "Pérez",
  "phone": "+52 55 1234 5678",
  "occupation": "Contadora"
}
```

Returns 200 with the updated tutor detail. Returns 404 when not found.

`POST /tutors/{tutorUuid}/students/{studentUuid}` requires `user.create`. Links a tutor to a student. If this is the student's first active tutor link and the student has not verified their email, a magic link is sent to the student. Optional body:

```json
{
  "relationship": "mother"
}
```

Valid `relationship` values: `mother`, `father`, `guardian`, `other`. Returns 200 on success. Returns 404 when tutor or student not found. Returns 409 when this specific tutor+student link already exists and is active.

---

## Tenant lifecycle

```
pending  → active     POST /auth/activate (owner sets password)
active   → suspended  PUT /staff/tenants/{uuid} with status=suspended (staff superadmin)
suspended → active    PUT /staff/tenants/{uuid} with status=active (staff superadmin)
```

`TenantMiddleware` rejects requests to a tenant with `status = 'pending'` with `403 Forbidden`. Only active tenants can receive authenticated requests via subdomain routing.
