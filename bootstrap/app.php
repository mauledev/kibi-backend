<?php

use App\Http\Middleware\TenantMiddleware;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (OwnerRoleAssignmentException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });
    })->create();
