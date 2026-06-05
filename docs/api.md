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

This header is **UI context only**. The frontend uses it to decide which dashboard to render. It does **not** participate in permission checks on the backend.

Permission checks always merge all active role assignments — no backend logic reads `X-Active-Role`. Reading an HTTP header inside a UseCase would couple the Application layer to HTTP, violating hexagonal architecture.

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

### Treasury (staff side — Superadmin in MVP)

```
GET    /staff/treasury/payments                       Paginated cross-tenant list of payments
GET    /staff/treasury/payments/{uuid}                Detail with state_log
POST   /staff/treasury/payments/{uuid}/approve        Approve a pending payment
POST   /staff/treasury/payments/{uuid}/reject         Reject a pending payment
```

Treasury is a **Softlinkia staff** module. In MVP the Superadmin operates the entire payment validation flow on their own (the Líder/Operador separation from the requirements doc is out of MVP scope). Authorization is enforced by the controller via an `is_staff` check; the route group is inside the `/staff` prefix and requires `auth:sanctum`. The seeded `treasury_operator` role (`is_system_role = true`, hierarchy level 2) exists for forward-compat once the post-MVP separation is added — it is not consulted today.

The repository operates **cross-tenant**: no `TenantContext` is applied. Filter to a single company via `?company_id=<tenant_uuid>` when needed.

State machine: every payment starts in `pending`. `approve` and `reject` are the only transitions out of `pending` exposed by MVP; both targets (`approved`, `rejected`) are terminal from the frontend's perspective. Attempting `approve` or `reject` on a non-pending payment returns 409 Conflict. A UUID that doesn't exist returns 404.

`?status` accepts `pending | approved | rejected | with_observation`. Status values are stored verbatim in English; the frontend maps each to a translated label in its i18n layer.

`?company_id` accepts the public UUID of a tenant (company). The controller resolves it to the internal id before constructing the criteria. An unknown UUID short-circuits to an empty result rather than 422.

`?school_id` accepts the public UUID of a school (any tenant). Same short-circuit-to-empty semantics as `?company_id`.

`?date_from` / `?date_to` accept `YYYY-MM-DD` and filter on `paid_at` inclusively. `date_to` must be ≥ `date_from`.

Pagination is fixed at 25 items per page for MVP; the response wraps `data` (array), `total`, `page` and `per_page` inside the standard `ApiResponse` envelope's `data` field.

Each list/detail item carries both `company_name` (tenant name) and `school_name` so the Superadmin can disambiguate cross-tenant rows at a glance.

`POST /approve` body: `{ "amount_received_cents": int, "note": string|null }`. The note is appended to the new `state_log` entry; the `amount_received_cents` is persisted on the payment row.

`POST /reject` body: `{ "reason": enum, "note": string|null }`. The `reason` enum is owned by `PaymentRejectReason` (`amount_mismatch | invalid_reference | illegible_receipt | transfer_not_found | other`). Reason and note are persisted inline on the new `state_log` entry.

Payment documents (attached receipts) are **not** exposed in MVP — there is no upload endpoint for the Owner to submit them, so the detail response intentionally omits the `documents` field. The upload + download flow is tracked in `docs/post-mvp.md` PM-004 and will be reintroduced when the Owner-side payment submission feature is built.

### Roles and permissions

```
GET    /roles                                              List roles for current tenant
POST   /roles                                             Create role (requires manage.permissions)
GET    /roles/{uuid}                                 Get role with its permissions
PUT    /roles/{uuid}                                 Update role
DELETE /roles/{uuid}                                 Delete role
GET    /permissions                                       List permissions grouped by category
POST   /roles/{uuid}/permissions                     Assign permission to role
DELETE /roles/{uuid}/permissions/{permission_uuid}   Revoke permission from role
POST   /users/{uuid}/roles                           Assign role to user
DELETE /users/{uuid}/roles/{role_uuid}          Revoke role from user
```

---

## Tenant lifecycle

```
pending  → active     POST /auth/activate (owner sets password)
active   → suspended  PUT /staff/tenants/{uuid} with status=suspended (staff superadmin)
suspended → active    PUT /staff/tenants/{uuid} with status=active (staff superadmin)
```

`TenantMiddleware` rejects requests to a tenant with `status = 'pending'` with `403 Forbidden`. Only active tenants can receive authenticated requests via subdomain routing.
