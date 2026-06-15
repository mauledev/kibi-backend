<?php

namespace App\Providers;

use App\Common\Audit\AuditLogger;
use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\LaravelMailer;
use App\Common\Mail\MailerInterface;
use App\Common\School\SchoolContext;
use App\Common\Staff\StaffContext;
use App\Common\Tenant\EloquentTenantRepository;
use App\Common\Tenant\TenantContext;
use App\Common\Tenant\TenantRepositoryInterface;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\RolePermissionController;
use App\Http\Middleware\TenantMiddleware;
use App\Models\User;
use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use App\Modules\Auth\Application\UseCases\AcceptPolicy\AcceptPolicyUseCase;
use App\Modules\Auth\Application\UseCases\ActivateAccount\ActivateAccountUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetMeUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetStaffMeUseCase;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\OAuthLogin\OAuthLoginUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\IssueStaffSessionUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactorLogin\StartTwoFactorSetupUseCase;
use App\Modules\Auth\Domain\Contracts\ActivationRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\OAuthProviderInterface;
use App\Modules\Auth\Domain\Contracts\PolicyAcceptanceRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Gateways\StubOAuthProvider;
use App\Modules\Auth\Infrastructure\Repositories\CacheTwoFactorChallengeRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentActivationRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentGlobalUserRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentPolicyAcceptanceRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentStaffUserRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentTwoFactorRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use App\Modules\Auth\Infrastructure\Services\Google2faService;
use App\Modules\Auth\Infrastructure\Services\SanctumTokenService;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Infrastructure\Repositories\EloquentOnboardingRepository;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleUseCase;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleUseCase;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesUseCase;
use App\Modules\Roles\Application\UseCases\RevokePermissionFromRole\RevokePermissionFromRoleUseCase;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Infrastructure\Repositories\EloquentGlobalRoleRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentPermissionRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentRoleRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentStaffRoleRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentUserRoleAssignmentRepository;
use App\Modules\Staff\Application\UseCases\ApproveSuperadminCreation\ApproveSuperadminCreationUseCase;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelUseCase;
use App\Modules\Staff\Domain\Contracts\StaffPersonnelReadRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\StaffWorkScheduleRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Infrastructure\Repositories\EloquentStaffPersonnelReadRepository;
use App\Modules\Staff\Infrastructure\Repositories\EloquentStaffWorkScheduleRepository;
use App\Modules\Staff\Infrastructure\Repositories\EloquentSuperadminApprovalRepository;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Infrastructure\Repositories\EloquentStudentRepository;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantUseCase;
use App\Modules\Tenant\Application\UseCases\GetTenantInfo\GetTenantInfoUseCase;
use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface as TenantModuleRepositoryInterface;
use App\Modules\Tenant\Infrastructure\Repositories\EloquentTenantRepository as TenantModuleEloquentRepository;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Infrastructure\Repositories\EloquentTutorRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // --- Common ---
        $this->app->bind(AuditLoggerInterface::class, AuditLogger::class);
        $this->app->bind(TenantRepositoryInterface::class, EloquentTenantRepository::class);
        $this->app->bind(MailerInterface::class, LaravelMailer::class);

        // --- Tenant module ---
        $this->app->bind(TenantModuleRepositoryInterface::class, TenantModuleEloquentRepository::class);
        $this->app->bind(GlobalUserRepositoryInterface::class, EloquentGlobalUserRepository::class);
        $this->app->bind(ActivationRepositoryInterface::class, EloquentActivationRepository::class);

        // GetTenantInfoUseCase — looks up a tenant by slug, no TenantContext required
        $this->app->when(GetTenantInfoUseCase::class)
            ->needs(TenantModuleRepositoryInterface::class)
            ->give(TenantModuleEloquentRepository::class);

        // CreateTenantUseCase — tenant-scoped role assignment repo, no TenantContext
        $this->app->when(CreateTenantUseCase::class)
            ->needs(UserRoleAssignmentRepositoryInterface::class)
            ->give(EloquentUserRoleAssignmentRepository::class);

        // --- Staff module ---
        $this->app->bind(
            StaffWorkScheduleRepositoryInterface::class,
            EloquentStaffWorkScheduleRepository::class
        );

        $this->app->bind(
            StaffPersonnelReadRepositoryInterface::class,
            EloquentStaffPersonnelReadRepository::class
        );

        // CreatePersonnelUseCase runs on staff routes (no TenantContext): resolve the
        // staff-scoped role repo (is_system_role = true) and the assignment repo directly.
        $this->app->when(CreatePersonnelUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        $this->app->when(CreatePersonnelUseCase::class)
            ->needs(UserRoleAssignmentRepositoryInterface::class)
            ->give(EloquentUserRoleAssignmentRepository::class);

        // Superadmin dual-control creation (SCRUM-520)
        $this->app->bind(
            SuperadminApprovalRepositoryInterface::class,
            EloquentSuperadminApprovalRepository::class
        );

        // ApproveSuperadminCreationUseCase runs on staff routes (no TenantContext):
        // same contextual bindings as CreatePersonnelUseCase.
        $this->app->when(ApproveSuperadminCreationUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        $this->app->when(ApproveSuperadminCreationUseCase::class)
            ->needs(UserRoleAssignmentRepositoryInterface::class)
            ->give(EloquentUserRoleAssignmentRepository::class);

        // ActivateAccountUseCase — uses global role repo (no TenantContext available during activation)
        $this->app->when(ActivateAccountUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentGlobalRoleRepository::class);

        // --- Auth module ---
        $this->app->bind(TokenServiceInterface::class, SanctumTokenService::class);
        $this->app->bind(OAuthProviderInterface::class, StubOAuthProvider::class);

        // Responsible Use Policy (PUR) acceptance — SCRUM-520
        $this->app->bind(
            PolicyAcceptanceRepositoryInterface::class,
            EloquentPolicyAcceptanceRepository::class,
        );

        // Checker reads version + required roles from config once (single source).
        $this->app->singleton(PolicyAcceptanceChecker::class, fn ($app) => new PolicyAcceptanceChecker(
            $app->make(PolicyAcceptanceRepositoryInterface::class),
            (string) config('policies.pur.version'),
            (array) config('policies.pur.required_roles'),
        ));

        $this->app->when(AcceptPolicyUseCase::class)
            ->needs('$version')
            ->giveConfig('policies.pur.version');

        // Two-factor (TOTP) base — reusable engine + persistence
        $this->app->bind(
            TwoFactorServiceInterface::class,
            Google2faService::class,
        );
        $this->app->bind(
            TwoFactorRepositoryInterface::class,
            EloquentTwoFactorRepository::class,
        );
        // Short-lived login challenge store (cache-backed, TTL from config)
        $this->app->bind(
            TwoFactorChallengeRepositoryInterface::class,
            fn () => new CacheTwoFactorChallengeRepository((int) config('twofactor.challenge_ttl', 600)),
        );

        // Tenant login — scoped by TenantContext
        $this->app->when(LoginUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        // OAuth login — scoped by TenantContext
        $this->app->when(OAuthLoginUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        // Staff login — scoped by tenant_id IS NULL, no TenantContext
        $this->app->when(StaffLoginUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentStaffUserRepository::class);

        $this->app->when(StaffLoginUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        // Staff session issuer — reused by the 2FA completion endpoints
        $this->app->when(IssueStaffSessionUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentStaffUserRepository::class);

        $this->app->when(IssueStaffSessionUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        // Staff 2FA enrollment at first login
        $this->app->when(StartTwoFactorSetupUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentStaffUserRepository::class);

        $this->app->when(StartTwoFactorSetupUseCase::class)
            ->needs('$issuer')
            ->giveConfig('twofactor.issuer');

        // Get me — tenant
        $this->app->when(GetMeUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        // Get me — staff
        $this->app->when(GetStaffMeUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentStaffUserRepository::class);

        $this->app->when(GetStaffMeUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        // AssignRoleToUser / RevokeRoleFromUser — resolve target user via tenant-scoped repository
        $this->app->when(AssignRoleToUserUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        $this->app->when(RevokeRoleFromUserUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        // --- Roles module ---
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
        $this->app->bind(UserRoleAssignmentRepositoryInterface::class, EloquentUserRoleAssignmentRepository::class);
        $this->app->bind(SchoolRepositoryInterface::class, EloquentSchoolRepository::class);

        // --- Schools module ---
        $this->app->bind(
            \App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface::class,
            \App\Modules\Schools\Infrastructure\Repositories\EloquentSchoolRepository::class
        );

        // --- Staff role controllers — use EloquentStaffRoleRepository ---
        // The contextual binding on the controller does not propagate to UseCases resolved
        // inside it, so we use factory closures to wire the correct repository explicitly.

        $this->app->when(RoleController::class)
            ->needs(ListRolesUseCase::class)
            ->give(function ($app) {
                return new ListRolesUseCase(
                    $app->make(EloquentStaffRoleRepository::class)
                );
            });

        $this->app->when(RoleController::class)
            ->needs(GetRoleUseCase::class)
            ->give(function ($app) {
                return new GetRoleUseCase(
                    $app->make(EloquentStaffRoleRepository::class),
                    $app->make(PermissionRepositoryInterface::class)
                );
            });

        $this->app->when(RolePermissionController::class)
            ->needs(AssignPermissionToRoleUseCase::class)
            ->give(function ($app) {
                return new AssignPermissionToRoleUseCase(
                    $app->make(EloquentStaffRoleRepository::class),
                    $app->make(PermissionRepositoryInterface::class),
                    $app->make(AuditLoggerInterface::class)
                );
            });

        $this->app->when(RolePermissionController::class)
            ->needs(RevokePermissionFromRoleUseCase::class)
            ->give(function ($app) {
                return new RevokePermissionFromRoleUseCase(
                    $app->make(EloquentStaffRoleRepository::class),
                    $app->make(PermissionRepositoryInterface::class),
                    $app->make(AuditLoggerInterface::class)
                );
            });
        // --- Onboarding module ---
        $this->app->bind(
            OnboardingRepositoryInterface::class,
            EloquentOnboardingRepository::class
        );

        // --- User module ---
        $this->app->bind(
            \App\Modules\User\Domain\Contracts\UserRepositoryInterface::class,
            \App\Modules\User\Infrastructure\Repositories\EloquentUserRepository::class
        );

        // --- Student module ---
        $this->app->bind(
            StudentRepositoryInterface::class,
            EloquentStudentRepository::class
        );

        // --- Tutor module ---
        $this->app->bind(
            TutorRepositoryInterface::class,
            EloquentTutorRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();
    }

    /**
     * Register authorization gates for the roles and permissions system.
     *
     * Two mechanisms work in tandem:
     *
     * 1. Gate::before — Two explicit bypasses, mutually exclusive by context:
     *    a) Owner bypass (tenant routes): the user whose id matches TenantContext::ownerId
     *       is granted every ability unconditionally.
     *    b) Superadmin bypass (staff routes): a staff user holding any is_system_role = true
     *       role is granted every ability unconditionally.
     *    Context is detected via container bindings: TenantContext on tenant routes,
     *    StaffContext on staff routes.
     *
     * 2. Gate::after — Dynamic permission gate: for every other user we load the merged
     *    set of permission slugs from all active role assignments and check whether the
     *    requested ability slug is present. Returning null delegates to any additional
     *    gates or policies.
     */
    private function registerGates(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            // Owner bypass — tenant routes only (TenantContext is bound by TenantMiddleware).
            // Owner identity comes from tenants.owner_id, not from role assignments.
            if (app()->bound(TenantContext::class)) {
                $context = app(TenantContext::class);

                return $context->ownerId === $user->id ? true : null;
            }

            // Superadmin bypass — staff routes only (StaffContext is bound by StaffMiddleware).
            // Any staff user holding a role with is_system_role = true gets full access.
            if (app()->bound(StaffContext::class) && $user->is_staff && $user->hasActiveSystemRole()) {
                return true;
            }

            return null;
        });

        // Dynamic permission gate — checks effective permissions for the current school context.
        // Includes gestor bypass: gestores have all permissions within their assigned schools.
        // Gate::after fires for every ability not short-circuited by Gate::before.
        Gate::after(function (User $user, string $ability): ?bool {
            $schoolId = app()->bound(SchoolContext::class)
                ? app(SchoolContext::class)->schoolId
                : null;

            // Gestor bypass — school level, all permissions in assigned schools
            if ($schoolId !== null && $user->isGestorOfSchool($schoolId)) {
                return true;
            }

            return $user->hasPermissionTo($ability, $schoolId) ? true : null;
        });
    }

    /**
     * Register named rate limiters.
     *
     * "login" throttles authentication attempts with two stacked limits, both from
     * config/auth.php (env-driven, replacing the throttle:5,15 once inlined in routes):
     *
     *  1. Per credential — scope (tenant slug or staff) + email + IP. Blocks brute
     *     force on one account without letting an attacker lock a victim out from
     *     a different IP (the key includes the IP on purpose, Fortify-style).
     *  2. Per IP backstop — caps email spraying from a single source. Higher ceiling
     *     because one school NAT can legitimately funnel many users through one IP.
     *
     * Requires TrustProxies (TRUSTED_PROXIES env, bootstrap/app.php) in production,
     * otherwise $request->ip() is the load balancer's address and every user shares
     * the same backstop bucket.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $maxAttempts = (int) config('auth.login_throttle.max_attempts');
            $decayMinutes = (int) config('auth.login_throttle.decay_minutes');
            $ipMaxAttempts = (int) config('auth.login_throttle.ip_max_attempts');

            // The throttle middleware runs BEFORE TenantMiddleware (framework
            // middleware priority hoists it), so TenantContext is never bound
            // here — the tenant scope must be derived from the request itself.
            $scope = $request->routeIs('staff.*')
                ? 'staff'
                : 'tenant:'.TenantMiddleware::resolveSlug($request);

            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes($decayMinutes, $maxAttempts)
                    ->by('login:'.$scope.'|'.hash('sha256', $email.'|'.$request->ip())),
                Limit::perMinutes($decayMinutes, $ipMaxAttempts)
                    ->by('login-ip:'.$request->ip()),
            ];
        });
    }
}
