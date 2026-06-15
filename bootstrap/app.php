<?php

use App\Http\Middleware\SchoolMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'school' => SchoolMiddleware::class,
        ]);

        // Trust the load balancer / reverse proxy so $request->ip() resolves to the
        // real client IP (login throttle keys + audit ip) instead of the proxy's.
        // TRUSTED_PROXIES = comma-separated IPs/CIDRs of the LB (e.g. 10.0.0.0/8);
        // leave empty in local — no proxy is trusted and behavior is unchanged.
        // env() on purpose: this callback runs before config files are loaded.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));

        if ($trustedProxies !== []) {
            // X-Forwarded-Host is deliberately NOT trusted: TenantMiddleware resolves
            // the tenant from the Host header, so honouring a client-controllable
            // forwarded host would allow tenant spoofing. If the production LB is
            // AWS ELB/ALB, switch headers to Request::HEADER_X_FORWARDED_AWS_ELB.
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (OwnerRoleAssignmentException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });
    })->create();
