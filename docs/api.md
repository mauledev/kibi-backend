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
X-Active-Role: {role_public_id}
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

Internal `id` (BIGSERIAL) is never exposed in any endpoint. All routes and responses use `public_id` (UUID):

```
GET /schools/{public_id}
GET /users/{public_id}
GET /roles/{public_id}
```

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
GET    /roles/{public_id}                                 Get role with its permissions
PUT    /roles/{public_id}                                 Update role
DELETE /roles/{public_id}                                 Delete role
GET    /permissions                                       List permissions grouped by category
POST   /roles/{public_id}/permissions                     Assign permission to role
DELETE /roles/{public_id}/permissions/{permission_public_id}   Revoke permission from role
POST   /users/{public_id}/roles                           Assign role to user
DELETE /users/{public_id}/roles/{role_public_id}          Revoke role from user
```
