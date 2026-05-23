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

When a user holds multiple roles, every authenticated request must include the active role:

```
X-Active-Role: {role_public_id}
```

UseCases read this header to validate that the user actually holds the declared role before executing permission-gated operations.

The header is required on all endpoints that perform write operations or permission-gated reads. Authentication endpoints (login, logout) are exempt.

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
POST   /auth/google              OAuth login/register via Google access token
POST   /auth/microsoft           OAuth login/register via Microsoft access token
```

#### OAuth flow (stateless)

The frontend handles the OAuth redirect with the provider. Once the user authorizes, the frontend sends the provider's access token to the backend:

```json
POST /auth/google
{ "access_token": "{google_access_token}" }
```

The backend validates the token with the provider via Socialite, finds or creates the user by `google_id` / `microsoft_id`, and returns a Sanctum token. The backend never handles OAuth redirects — that is the frontend's responsibility.

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
