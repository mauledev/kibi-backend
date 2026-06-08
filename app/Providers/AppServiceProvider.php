<?php

namespace App\Providers;

use App\Common\Audit\AuditLogger;
use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\LaravelMailer;
use App\Common\Mail\MailerInterface;
use App\Common\School\SchoolContext;
use App\Common\Tenant\EloquentTenantRepository;
use App\Common\Tenant\TenantContext;
use App\Common\Tenant\TenantRepositoryInterface;
use App\Models\User;
use App\Modules\Auth\Application\UseCases\ActivateAccount\ActivateAccountUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetMeUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetStaffMeUseCase;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\OAuthLogin\OAuthLoginUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Contracts\ActivationRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\OAuthProviderInterface;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Gateways\StubOAuthProvider;
use App\Modules\Auth\Infrastructure\Repositories\EloquentActivationRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentGlobalUserRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentStaffUserRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use App\Modules\Auth\Infrastructure\Services\SanctumTokenService;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
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
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelUseCase;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantUseCase;
use App\Modules\Tenant\Application\UseCases\GetTenantInfo\GetTenantInfoUseCase;
use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface as TenantModuleRepositoryInterface;
use App\Modules\Tenant\Infrastructure\Repositories\EloquentTenantRepository as TenantModuleEloquentRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // CreatePersonnelUseCase runs on staff routes (no TenantContext): resolve the
        // staff-scoped role repo (is_system_role = true) and the assignment repo directly.
        $this->app->when(CreatePersonnelUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        $this->app->when(CreatePersonnelUseCase::class)
            ->needs(UserRoleAssignmentRepositoryInterface::class)
            ->give(EloquentUserRoleAssignmentRepository::class);

        // ActivateAccountUseCase — uses global role repo (no TenantContext available during activation)
        $this->app->when(ActivateAccountUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentGlobalRoleRepository::class);

        // --- Auth module ---
        $this->app->bind(TokenServiceInterface::class, SanctumTokenService::class);
        $this->app->bind(OAuthProviderInterface::class, StubOAuthProvider::class);

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
    }

    /**
     * Register authorization gates for the roles and permissions system.
     *
     * Two mechanisms work in tandem:
     *
     * 1. Gate::before — Owner bypass: the user whose id matches TenantContext::ownerId
     *    is granted every ability unconditionally. Staff routes do not bind TenantContext,
     *    so this bypass is skipped entirely for staff requests.
     *
     * 2. Gate::after — Dynamic permission gate: for every other user we load the merged
     *    set of permission slugs from all active role assignments and check whether the
     *    requested ability slug is present. Returning null delegates to any additional
     *    gates or policies.
     */
    private function registerGates(): void
    {
        // Owner bypass — runs before any ability check.
        // Skipped on staff routes where TenantContext is never bound.
        // The owner identity comes from tenants.owner_id, not from role assignments.
        Gate::before(function (User $user, string $ability): ?bool {
            if (! app()->bound(TenantContext::class)) {
                return null;
            }

            $context = app(TenantContext::class);

            if ($context->ownerId === $user->id) {
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
}
