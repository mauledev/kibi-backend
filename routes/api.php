<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Roles\PermissionController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RolePermissionController;
use App\Http\Controllers\Roles\UserRoleController;
use App\Http\Controllers\Staff\TenantController;
use App\Http\Controllers\User\UserController;
use App\Http\Response\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health check
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => ApiResponse::success(['status' => 'ok']));

/*
|--------------------------------------------------------------------------
| Staff routes — app.kibi.com
| No tenant middleware. Users have tenant_id IS NULL.
|--------------------------------------------------------------------------
*/
Route::prefix('staff')->group(function () {
    // Public
    Route::post('/auth/login', [AuthController::class, 'staffLogin'])->middleware('throttle:5,15')->name('staff.auth.login');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'staffMe'])->name('staff.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('staff.auth.logout');

        Route::post('/tenants', [TenantController::class, 'store'])->name('staff.tenants.store');
    });
});

/*
|--------------------------------------------------------------------------
| Public routes — no tenant middleware, no auth
|--------------------------------------------------------------------------
*/
Route::get('/auth/tenant-info', [AuthController::class, 'tenantInfo'])->name('auth.tenant-info');
Route::post('/auth/activate', [AuthController::class, 'activate'])->name('auth.activate');

/*
|--------------------------------------------------------------------------
| Tenant routes — {tenant_slug}.kibi.com
| TenantMiddleware resolves TenantContext from subdomain.
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () {
    // Public (login needs tenant context to scope user lookup)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,15')->name('auth.login');
    Route::post('/auth/oauth/{provider}', [AuthController::class, 'oauthLogin'])->whereIn('provider', ['google', 'microsoft'])->name('auth.oauth');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('users', UserController::class);

        // Roles and Permissions
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{uuid}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{uuid}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{uuid}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::post('/roles/{uuid}/permissions', [RolePermissionController::class, 'store'])
            ->name('roles.permissions.store');
        Route::delete('/roles/{uuid}/permissions/{permission_uuid}', [RolePermissionController::class, 'destroy'])
            ->name('roles.permissions.destroy');

        Route::post('/users/{uuid}/roles', [UserRoleController::class, 'store'])
            ->name('users.roles.store');
        Route::delete('/users/{uuid}/roles/{role_uuid}', [UserRoleController::class, 'destroy'])
            ->name('users.roles.destroy');
    });
});
