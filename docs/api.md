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

### Owner

Owner bypasses all permission checks via `Gate::before`. No permission gates are evaluated for owner requests.

```php
Gate::before(function (User $user) {
    if ($user->hasRole('owner')) {
        return true;
    }
});
```

### School users

Permission checks use the slug convention:

```php
$this->authorize('grade.publish');
$this->authorize('payment.approve');
$this->authorize('manage.permissions');
```

### Softlinkia staff

Staff roles are checked by slug directly — no permission table is involved:

```php
$this->authorize('softlinkia.support.l1');
```

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

Routes are split into two groups in `routes/api.php`:

```php
// Staff routes — app.kibi.com, no tenant middleware
Route::prefix('staff')->middleware(['auth:sanctum'])->group(function () {
    // staff-only endpoints
});

// Tenant routes — {tenant_slug}.kibi.com
Route::middleware(['tenant', 'auth:sanctum'])->group(function () {
    // all school-facing endpoints
});
```

Authentication endpoints (login, logout) sit outside both groups — no `auth:sanctum` required.

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
POST   /staff/auth/login         Authenticate Softlinkia staff
GET    /staff/auth/me            Return authenticated staff user data
POST   /staff/auth/logout        Revoke staff token
GET    /staff/tenants            List all tenants (paginated) with embedded owners
POST   /staff/tenants            Create a new tenant + owner (requires auth:sanctum)
GET    /staff/tenants/{uuid}     Get a single tenant with embedded owner
PUT    /staff/tenants/{uuid}     Update a tenant's name, slug, and status
DELETE /staff/tenants/{uuid}     Soft-delete a tenant
```

`GET /staff/tenants` returns 200 with a paginated list of tenants. Accepts a `page` query parameter (default: 1, page size: 20). Each item includes a compact owner shape (`uuid`, `email`, `full_name`) and a `created_at` ISO 8601 timestamp. The response envelope includes a `meta.pagination` object with `total`, `per_page`, `current_page`, and `last_page`.

`POST /staff/tenants` creates the tenant with `status = 'pending'`, creates the owner user with no password, assigns the `owner` role, and sends an activation email with a 48h signed URL. Returns 201 with the tenant and embedded owner. Returns 409 when the slug or email is already taken.

`GET /staff/tenants/{uuid}` returns 200 with the full tenant and embedded owner. Returns 404 when not found. The owner shape includes `uuid`, `email`, `first_name`, `last_name_paternal`, `last_name_maternal`, and `full_name`.

`PUT /staff/tenants/{uuid}` accepts `{ "name": "...", "slug": "...", "status": "..." }`. Valid status values: `pending`, `active`, `suspended`. Returns 200 with the updated tenant. Returns 404 when not found. Returns 409 when the new slug is already taken by another tenant.

`DELETE /staff/tenants/{uuid}` soft-deletes the tenant. Returns 200 with a success message. Returns 404 when not found.

### Schools

```
GET    /schools                          List schools of the current tenant
POST   /schools                          Create a school
GET    /schools/{uuid}                   Get a single school
PUT    /schools/{uuid}                   Update mutable fields (partial)
POST   /schools/{uuid}/deactivate        Soft-delete a school
```

`GET /schools` accepts an optional `?status=` query param to narrow the result set:

| Value          | Meaning                                                                                |
|----------------|----------------------------------------------------------------------------------------|
| *(omitted)*    | Equivalent to `active` — the default. There is no separate "no-filter" mode.           |
| `active`       | `status='active'` AND not soft-deleted.                                                |
| `deactivated`  | Only soft-deleted rows.                                                                |
| `all`          | Every row, including soft-deleted and any rows with a non-`active` status (suspended). |

This is the canonical pattern for list endpoints that need to expose soft-deleted rows alongside lifecycle states. The filter values are a Domain enum (`SchoolListFilter`); the FormRequest validates via `Rule::enum(...)` and the controller never inlines strings. The repository contract accepts a **Criteria** object (`SchoolListCriteria`) rather than loose primitives, so adding pagination, search or sorting later does not break callers. The Criteria's `status` field is non-nullable with `Active` as its default — "include everything" is expressed exclusively as `All`, eliminating the previous `null`/`All` ambiguity. The repository maps `Deactivated` to `onlyTrashed()` and `All` to `withTrashed()`; the soft-delete column stays an Infrastructure concern. Resources expose `deleted_at` so the frontend can render deactivated rows distinctly.

### Users

```
GET    /users                   List users in the current tenant (paginated, filterable)
GET    /users/{uuid}            Get a single user with full detail
POST   /users                   Create a user (not yet implemented — returns 501)
PUT    /users/{uuid}            Update a user (not yet implemented — returns 501)
DELETE /users/{uuid}            Delete a user (not yet implemented — returns 501)
```

`GET /users` returns 200 with a paginated list of tenant users. Authorization requires the `user.view` permission. Accepts optional query parameters:

| Parameter | Type | Description |
|---|---|---|
| `q` | string (max 255) | Free-text search across `first_name`, `last_name_paternal`, `last_name_maternal`, `email` (Postgres ILIKE, case-insensitive) |
| `filter[role]` | string or string[] | Filter by role slug(s). Users must hold an active assignment with at least one of the provided slugs. |
| `filter[status]` | string | Filter by lifecycle status. Allowed: `active`, `inactive`, `suspended`. |
| `page` | integer (min 1) | Page number. Default: 1. |
| `per_page` | integer (1–100) | Items per page. Default: 20. |

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
        { "slug": "teacher", "name": "Teacher", "school_uuid": "..." }
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

`GET /users/{uuid}` returns 200 with the full user detail. Authorization requires `user.view`. Returns 404 when the UUID does not exist within the current tenant.

Detail response includes the list fields plus: `first_name`, `last_name_paternal`, `last_name_maternal`.

Permission slug used: `user.view` (seeded under `school/director` category — also held by `school_registrar`, `prefect`, `finance`, `hr`, and `academic_coordinator` via their respective category slugs).

---

### Roles and permissions

```
GET    /roles                                                        List roles for current tenant
POST   /roles/custom                                                 Create a custom role (owner and gestor only)
GET    /roles/{uuid}                                                 Get role with its effective permissions
PUT    /roles/{uuid}                                                 Update role name (custom roles only)
DELETE /roles/{uuid}                                                 Delete role (custom roles only)
GET    /permissions                                                  List permissions (filtered by role category if role_uuid provided)
POST   /roles/{uuid}/permissions                                     Assign permission to role (category-bound)
DELETE /roles/{uuid}/permissions/{permission_uuid}                   Revoke permission from role
POST   /users/{uuid}/roles                                           Assign role to user (owner, gestor, director only)
DELETE /users/{uuid}/roles/{role_uuid}                               Revoke role from user
POST   /users/{uuid}/assignments/{assignment_uuid}/denials            Deny a permission for a specific assignment
DELETE /users/{uuid}/assignments/{assignment_uuid}/denials/{perm_uuid} Restore a denied permission
PUT    /tenant/custom-roles-limit                                    Configure max custom roles (owner only)
```

`POST /roles/custom` creates a custom role (`category_id = NULL`) and assigns it to one or more schools. Requires the tenant's `custom_roles_limit` to be set and not exceeded. Body: `{ "name": "...", "school_uuids": ["..."] }`.

`GET /permissions` accepts an optional `?role_uuid=` query param. When provided, returns only permissions belonging to that role's category. When absent (or for custom roles), returns all permissions grouped by category.

`POST /users/{uuid}/assignments/{assignment_uuid}/denials` subtracts a permission from a specific `user_role_assignments` row. Cannot be applied to owner or gestor assignments. Body: `{ "permission_uuid": "..." }`.

`PUT /tenant/custom-roles-limit` sets `tenants.custom_roles_limit` for the authenticated owner's tenant. Body: `{ "limit": 10 }` (1–50).

---

### Onboarding

```
GET    /onboarding/progress               Return current onboarding progress (auto-bootstraps)
POST   /onboarding/steps/company          Complete step 1 — company data
POST   /onboarding/steps/branding         Complete step 2 — branding
POST   /onboarding/steps/first-school     Complete step 3 — link first school
POST   /onboarding/steps/skip             501 — not implemented (MVP stub)
POST   /onboarding/steps/academic-template 501 — not implemented (MVP stub)
POST   /onboarding/steps/fiscal           501 — not implemented (MVP stub)
POST   /onboarding/steps/payment          501 — not implemented (MVP stub)
POST   /onboarding/steps/director         501 — not implemented (MVP stub)
```

All onboarding endpoints require `auth:sanctum` + `tenant` + `owner` middleware. The `owner` middleware (`EnsureUserIsTenantOwner`) checks `request.user.id === TenantContext::ownerId` — only the tenant owner can perform onboarding.

`GET /onboarding/progress` auto-bootstraps a missing record (handles legacy tenants). Returns an `OnboardingProgressResource` with `uuid`, `current_step`, `status` (effective — may be `suspended` when grace period expired), `steps[]`, `grace_period_ends_at`, `is_grace_period_expired`, `can_access_full_panel`, `created_at`, `updated_at`.

`POST /onboarding/steps/company` body: `business_name`, `rfc` (auto-uppercased), `fiscal_address` (nested object), `primary_contact_name`, `primary_contact_email`, `primary_contact_phone`. Returns 200 with updated progress. Idempotent.

`POST /onboarding/steps/branding` body: `logo_url`, `primary_color` (hex), `secondary_color` (hex). Requires step 1 completed (422 if not). Returns 200 with updated progress. Idempotent.

`POST /onboarding/steps/first-school` body: `school_id` (school UUID). Requires step 2 completed (422 if not). Returns 403 if school UUID does not belong to the tenant. Returns 200 with updated progress. Idempotent.

`BootstrapOnboardingUseCase` is called inside the `CreateTenantUseCase` transaction after tenant creation, so every new tenant gets an onboarding record immediately.

---

## Tenant lifecycle

```
pending  → active     POST /auth/activate (owner sets password)
active   → suspended  PUT /staff/tenants/{uuid} with status=suspended (staff superadmin)
suspended → active    PUT /staff/tenants/{uuid} with status=active (staff superadmin)
```

`TenantMiddleware` rejects requests to a tenant with `status = 'pending'` with `403 Forbidden`. Only active tenants can receive authenticated requests via subdomain routing.
