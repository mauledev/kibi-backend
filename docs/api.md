# API

Endpoint conventions, responses, authentication and error handling.

---

## ApiResponse

All JSON responses go through `app/Http/Response/ApiResponse`. Never use `response()->json()` directly.

```php
ApiResponse::success($data, $message)      // 200
ApiResponse::created($data)                // 201
ApiResponse::error($message, $status)      // generic error
ApiResponse::notFound()                    // 404
ApiResponse::unauthorized()               // 401
ApiResponse::forbidden()                  // 403
ApiResponse::conflict($message, $errors)  // 409
```

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
| `app.kibi.com` | Softlinkia staff | None — `tenant_id IS NULL` |
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
```

`POST /auth/oauth/{provider}` accepts `{ "access_token": "..." }` and returns the same `LoginOutput` as a password login. See `docs/oauth.md` for the full flow.

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
