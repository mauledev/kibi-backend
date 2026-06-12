<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Roles\AssignmentDenialController;
use App\Http\Controllers\Roles\CustomRoleLimitController;
use App\Http\Controllers\Roles\PermissionController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RolePermissionController;
use App\Http\Controllers\Roles\UserRoleController;
use App\Http\Controllers\Schools\RoleController as SchoolRoleController;
use App\Http\Controllers\Schools\RolePermissionController as SchoolRolePermissionController;
use App\Http\Controllers\Schools\SchoolController;
use App\Http\Controllers\Staff\RoleController as StaffRoleController;
use App\Http\Controllers\Staff\RolePermissionController as StaffRolePermissionController;
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
    Route::middleware(['auth:sanctum', 'staff'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'staffMe'])->name('staff.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('staff.auth.logout');

        Route::apiResource('tenants', TenantController::class)->names('staff.tenants');

        // Staff role management
        Route::get('/roles', [StaffRoleController::class, 'index'])->name('staff.roles.index');
        Route::get('/roles/{uuid}', [StaffRoleController::class, 'show'])->name('staff.roles.show');
        Route::post('/roles/{uuid}/permissions', [StaffRolePermissionController::class, 'store'])
            ->name('staff.roles.permissions.store');
        Route::delete('/roles/{uuid}/permissions/{permission_uuid}', [StaffRolePermissionController::class, 'destroy'])
            ->name('staff.roles.permissions.destroy');
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

        // All tenant resources are prefixed with /tenant
        Route::prefix('tenant')->group(function () {
            Route::apiResource('users', UserController::class);

            // Roles and Permissions
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');

            // Custom role creation — must be declared before /roles/{uuid} to avoid /roles/custom
            // being captured as a UUID segment.
            Route::post('/roles/custom', [RoleController::class, 'store'])->name('roles.custom.store');

            Route::get('/roles/{uuid}', [RoleController::class, 'show'])->name('roles.show');
            Route::put('/roles/{uuid}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{uuid}', [RoleController::class, 'destroy'])->name('roles.destroy');

            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

            Route::post('/roles/{uuid}/permissions', [RolePermissionController::class, 'store'])
                ->name('roles.permissions.store');
            Route::delete('/roles/{uuid}/permissions/{permission_uuid}', [RolePermissionController::class, 'destroy'])
                ->name('roles.permissions.destroy');

            // Configure tenant custom roles limit (owner only)
            Route::put('/custom-roles-limit', [CustomRoleLimitController::class, 'update'])
                ->name('tenant.custom-roles-limit.update');

            Route::post('/users/{uuid}/roles', [UserRoleController::class, 'store'])
                ->name('users.roles.store');
            Route::delete('/users/{uuid}/roles/{role_uuid}', [UserRoleController::class, 'destroy'])
                ->name('users.roles.destroy');

            // Permission denials on specific assignments
            Route::post('/users/{uuid}/assignments/{assignment_uuid}/denials', [AssignmentDenialController::class, 'store'])
                ->name('users.assignments.denials.store');
            Route::delete('/users/{uuid}/assignments/{assignment_uuid}/denials/{permission_uuid}', [AssignmentDenialController::class, 'destroy'])
                ->name('users.assignments.denials.destroy');

            Route::get('/school', [SchoolController::class, 'currentSchool'])
                ->middleware('school')
                ->name('school.current');

            Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
            Route::post('/schools', [SchoolController::class, 'store'])->name('schools.store');
            Route::get('/schools/{uuid}', [SchoolController::class, 'show'])->name('schools.show');
            Route::put('/schools/{uuid}', [SchoolController::class, 'update'])->name('schools.update');
            Route::post('/schools/{uuid}/deactivate', [SchoolController::class, 'deactivate'])->name('schools.deactivate');

            // Permissions scoped to a school and role category
            Route::get('/schools/{uuid}/permissions', [PermissionController::class, 'schoolIndex'])
                ->name('schools.permissions.index');

            // Roles available in a specific school
            Route::get('/schools/{uuid}/roles', [SchoolRoleController::class, 'index'])
                ->name('schools.roles.index');
            Route::post('/schools/{uuid}/roles', [SchoolRoleController::class, 'store'])
                ->name('schools.roles.store');
            Route::get('/schools/{uuid}/roles/{role_uuid}', [SchoolRoleController::class, 'show'])
                ->name('schools.roles.show');
            Route::put('/schools/{uuid}/roles/{role_uuid}', [SchoolRoleController::class, 'update'])
                ->name('schools.roles.update');
            Route::post('/schools/{uuid}/roles/{role_uuid}/permissions', [SchoolRolePermissionController::class, 'store'])
                ->name('schools.roles.permissions.store');
            Route::delete('/schools/{uuid}/roles/{role_uuid}/permissions/{permission_uuid}', [SchoolRolePermissionController::class, 'destroy'])
                ->name('schools.roles.permissions.destroy');
        });
    });
});
