<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PolicyAcceptanceController;
use App\Http\Controllers\Me\MeOnboardingController;
use App\Http\Controllers\Me\MeSchoolsController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Roles\AssignmentDenialController;
use App\Http\Controllers\Roles\CustomRoleLimitController;
use App\Http\Controllers\Roles\PermissionController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RolePermissionController;
use App\Http\Controllers\Roles\UserRoleController;
use App\Http\Controllers\Schools\RoleController as SchoolRoleController;
use App\Http\Controllers\Schools\RolePermissionController as SchoolRolePermissionController;
use App\Http\Controllers\Schools\SchoolController;
use App\Http\Controllers\Staff\PersonnelController;
use App\Http\Controllers\Staff\RoleController as StaffRoleController;
use App\Http\Controllers\Staff\RolePermissionController as StaffRolePermissionController;
use App\Http\Controllers\Staff\SuperadminApprovalController;
use App\Http\Controllers\Staff\TenantController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Treasury\PaymentController;
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
    Route::post('/auth/login', [AuthController::class, 'staffLogin'])->middleware('throttle:login')->name('staff.auth.login');

    // Public — 2FA login step (guarded by an opaque challenge token, not a session)
    Route::post('/auth/2fa/setup', [AuthController::class, 'twoFactorSetup'])->middleware('throttle:5,15')->name('staff.auth.2fa.setup');
    Route::post('/auth/2fa/confirm', [AuthController::class, 'twoFactorConfirm'])->middleware('throttle:5,15')->name('staff.auth.2fa.confirm');
    Route::post('/auth/2fa/challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:5,15')->name('staff.auth.2fa.challenge');

    // Authenticated
    Route::middleware(['auth:sanctum', 'staff'])->group(function () {
        // Always reachable, even while the Responsible Use Policy gate is pending:
        // the user needs their session and a way to accept the policy.
        Route::get('/auth/me', [AuthController::class, 'staffMe'])->name('staff.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('staff.auth.logout');
        Route::post('/auth/policy/accept', [PolicyAcceptanceController::class, 'accept'])->name('staff.auth.policy.accept');

        // App endpoints — blocked until the Responsible Use Policy is accepted.
        Route::middleware('policy.accepted')->group(function () {
            Route::apiResource('tenants', TenantController::class)->names('staff.tenants');

            // Treasury — payment validation (Superadmin operates this in MVP)
            Route::get('/treasury/payments', [PaymentController::class, 'index'])->name('staff.treasury.payments.index');
            Route::get('/treasury/payments/{uuid}', [PaymentController::class, 'show'])->name('staff.treasury.payments.show');
            Route::post('/treasury/payments/{uuid}/approve', [PaymentController::class, 'approve'])->name('staff.treasury.payments.approve');
            Route::post('/treasury/payments/{uuid}/reject', [PaymentController::class, 'reject'])->name('staff.treasury.payments.reject');

            // Staff role management
            Route::get('/roles', [StaffRoleController::class, 'index'])->name('staff.roles.index');
            Route::get('/roles/{uuid}', [StaffRoleController::class, 'show'])->name('staff.roles.show');
            Route::post('/roles/{uuid}/permissions', [StaffRolePermissionController::class, 'store'])
                ->name('staff.roles.permissions.store');
            Route::delete('/roles/{uuid}/permissions/{permission_uuid}', [StaffRolePermissionController::class, 'destroy'])
                ->name('staff.roles.permissions.destroy');

            // Backoffice staff personnel — Superadmin only (explicit check; no Gate on staff routes)
            Route::middleware('staff.superadmin')->group(function () {
                Route::get('/personnel', [PersonnelController::class, 'index'])->name('staff.personnel.index');
                Route::get('/personnel/{uuid}', [PersonnelController::class, 'show'])->name('staff.personnel.show');
                Route::post('/personnel', [PersonnelController::class, 'store'])->name('staff.personnel.store');

                // Superadmin dual-control creation ceremony
                Route::get('/superadmin/approvals', [SuperadminApprovalController::class, 'index'])->name('staff.superadmin.approvals.index');
                Route::post('/superadmin/approvals', [SuperadminApprovalController::class, 'store'])->name('staff.superadmin.approvals.store');
                Route::get('/superadmin/approvals/{uuid}', [SuperadminApprovalController::class, 'show'])->name('staff.superadmin.approvals.show');
                Route::post('/superadmin/approvals/{uuid}/approve', [SuperadminApprovalController::class, 'approve'])->name('staff.superadmin.approvals.approve');
                Route::post('/superadmin/approvals/{uuid}/reject', [SuperadminApprovalController::class, 'reject'])->name('staff.superadmin.approvals.reject');
            });
        });
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
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/auth/oauth/{provider}', [AuthController::class, 'oauthLogin'])->whereIn('provider', ['google', 'microsoft'])->name('auth.oauth');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // All tenant resources are prefixed with /tenant
        Route::prefix('tenant')->group(function () {
            Route::apiResource('users', UserController::class);

            // Current user shortcuts
            Route::get('/me/onboarding', [MeOnboardingController::class, 'show'])->name('me.onboarding.show');
            Route::get('/me/schools', [MeSchoolsController::class, 'show'])->name('me.schools.show');

            // Roles and Permissions
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');

            // Custom role creation — declared before /roles/{uuid} to avoid /roles/custom
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

            // Students
            Route::middleware('school')->group(function (): void {
                Route::post('/students', [StudentController::class, 'store'])->name('students.store');
            });
            Route::get('/students', [StudentController::class, 'index'])->middleware('school')->name('students.index');
            Route::get('/students/{uuid}', [StudentController::class, 'show'])->name('students.show');
            Route::put('/students/{uuid}', [StudentController::class, 'update'])->name('students.update');

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
});
