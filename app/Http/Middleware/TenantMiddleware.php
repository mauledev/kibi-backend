<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Common\Tenant\TenantContext;
use App\Http\Response\ApiResponse;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveSlug($request);

        $tenant = Tenant::where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (! $tenant) {
            return ApiResponse::notFound('Tenant not found');
        }

        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $tenant->id,
        ));

        return $next($request);
    }

    private function resolveSlug(Request $request): string
    {
        // In local development, accept X-Tenant-Slug header as fallback
        // when there is no real subdomain (e.g. localhost:8000)
        $host = $request->getHost();

        if (! str_contains($host, '.') && (app()->isLocal() || app()->runningUnitTests())) {
            return $request->header('X-Tenant-Slug', '');
        }

        return explode('.', $host)[0];
    }
}
