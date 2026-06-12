<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Me\MeOnboardingController;
use App\Http\Controllers\Me\MeSchoolsController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Roles\AssignmentDenialController;
use App\Http\Controllers\Roles\CustomRoleLimitController;
use App\Http\Controllers\Roles\PermissionController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RolePermissionController;
use App\Http\Controllers\Roles\UserRoleController;
use App\Http\Controllers\Schools\SchoolController;
use App\Http\Controllers\Staff\TenantController;
use App\Http\Controllers\Tutor\TutorController;
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

        Route::apiResource('tenants', TenantController::class)->names('staff.tenants');
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

        // Onboarding progress of the current user (derived %, no storage).
        Route::get('/me/onboarding', [MeOnboardingController::class, 'show'])->name('me.onboarding.show');
        Route::get('/me/schools', [MeSchoolsController::class, 'show'])->name('me.schools.show');

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
        Route::put('/tenant/custom-roles-limit', [CustomRoleLimitController::class, 'update'])
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

        // Tutors
        Route::middleware('school')->group(function (): void {
            Route::post('/tutors', [TutorController::class, 'store'])->name('tutors.store');
        });
        Route::get('/tutors', [TutorController::class, 'index'])->middleware('school')->name('tutors.index');
        Route::get('/tutors/{uuid}', [TutorController::class, 'show'])->name('tutors.show');
        Route::put('/tutors/{uuid}', [TutorController::class, 'update'])->name('tutors.update');
        Route::post('/tutors/{tutorUuid}/students/{studentUuid}', [TutorController::class, 'linkStudent'])
            ->name('tutors.students.link');

        // Onboarding — owner-only enforcement lives inline in the controller (denyIfNotOwner)
        Route::prefix('onboarding')->group(function () {
            Route::get('/progress', [OnboardingController::class, 'getProgress'])
                ->name('onboarding.progress');
            Route::post('/steps/company', [OnboardingController::class, 'completeCompanyData'])
                ->name('onboarding.steps.company');
            Route::post('/steps/branding', [OnboardingController::class, 'completeBranding'])
                ->name('onboarding.steps.branding');
            Route::post('/steps/first-school', [OnboardingController::class, 'completeFirstSchool'])
                ->name('onboarding.steps.first-school');
        });
    });
});
