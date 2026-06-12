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

### Roles and permissions — Tenant scope

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

## Tenant lifecycle

```
pending  → active     POST /auth/activate (owner sets password)
active   → suspended  PUT /staff/tenants/{uuid} with status=suspended (staff superadmin)
suspended → active    PUT /staff/tenants/{uuid} with status=active (staff superadmin)
```

`TenantMiddleware` rejects requests to a tenant with `status = 'pending'` with `403 Forbidden`. Only active tenants can receive authenticated requests via subdomain routing.
