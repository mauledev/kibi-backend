<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\EloquentStaffUserRepository;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use App\Modules\Auth\Infrastructure\Services\SanctumTokenService;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Infrastructure\Repositories\EloquentPermissionRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentRoleRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentStaffRoleRepository;
use App\Modules\Roles\Infrastructure\Repositories\EloquentUserRoleAssignmentRepository;
use App\Common\Audit\AuditLogger;
use App\Common\Audit\AuditLoggerInterface;
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

        // --- Auth module ---
        $this->app->bind(TokenServiceInterface::class, SanctumTokenService::class);

        // Tenant login — scoped by TenantContext
        $this->app->when(LoginUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentUserRepository::class);

        // Staff login — scoped by tenant_id IS NULL, no TenantContext
        $this->app->when(StaffLoginUseCase::class)
            ->needs(UserRepositoryInterface::class)
            ->give(EloquentStaffUserRepository::class);

        $this->app->when(StaffLoginUseCase::class)
            ->needs(RoleRepositoryInterface::class)
            ->give(EloquentStaffRoleRepository::class);

        // --- Roles module ---
        $this->app->bind(RoleRepositoryInterface::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, EloquentPermissionRepository::class);
        $this->app->bind(UserRoleAssignmentRepositoryInterface::class, EloquentUserRoleAssignmentRepository::class);
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
     * 1. Gate::before — Owner bypass: any user holding an active 'owner' role
     *    assignment is granted every ability unconditionally. This runs before
     *    any Gate::define check.
     *
     * 2. Gate::define('*') dynamic gate — For every other user, we load the
     *    merged set of permission slugs from all active role assignments and
     *    check whether the requested ability slug is present in that set.
     *    Returning null delegates to any additional gates or policies.
     */
    private function registerGates(): void
    {
        // Owner bypass — runs before any ability check
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('owner')) {
                return true;
            }

            return null; // let normal gate evaluation proceed
        });

        // Dynamic permission gate — checks merged permissions from all active roles.
        // Gate::after fires for every ability not short-circuited by Gate::before,
        // giving all authenticated users a permission-based fallback check.
        Gate::after(function (User $user, string $ability): ?bool {
            return $user->hasPermissionTo($ability) ? true : null;
        });
    }
}
