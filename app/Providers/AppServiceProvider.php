<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\EloquentUserRepository;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;

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
            fn($app) => new LoginUseCase(
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
