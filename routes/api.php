<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Roles\PermissionController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RolePermissionController;
use App\Http\Controllers\Roles\UserRoleController;
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
    Route::post('/auth/login', [AuthController::class, 'staffLogin'])->name('staff.auth.login');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('staff.auth.logout');
    });
});

/*
|--------------------------------------------------------------------------
| Tenant routes — {tenant_slug}.kibi.com
| TenantMiddleware resolves TenantContext from subdomain.
|--------------------------------------------------------------------------
*/
Route::middleware('tenant')->group(function () {
    // Public (login needs tenant context to scope user lookup)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('users', UserController::class);

        // Roles and Permissions
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{public_id}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{public_id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{public_id}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::post('/roles/{public_id}/permissions', [RolePermissionController::class, 'store'])
            ->name('roles.permissions.store');
        Route::delete('/roles/{public_id}/permissions/{permission_public_id}', [RolePermissionController::class, 'destroy'])
            ->name('roles.permissions.destroy');

        Route::post('/users/{public_id}/roles', [UserRoleController::class, 'store'])
            ->name('users.roles.store');
        Route::delete('/users/{public_id}/roles/{role_public_id}', [UserRoleController::class, 'destroy'])
            ->name('users.roles.destroy');
    });
});
