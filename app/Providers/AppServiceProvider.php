<?php

namespace App\Providers;

use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ============================================
        // Binding de interfaces a implementaciones
        // ============================================

        // Auth module bindings
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        // Use Cases
        $this->app->singleton(
            LoginUseCase::class,
            fn ($app) => new LoginUseCase(
                $app->make(UserRepositoryInterface::class)
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
