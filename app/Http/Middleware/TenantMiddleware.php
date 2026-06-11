<?php

namespace App\Http\Middleware;

use App\Common\Tenant\TenantContext;
use App\Common\Tenant\TenantRepositoryInterface;
use App\Http\Response\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveSlug($request);

        $tenant = $this->tenants->findBySlug($slug);

        if ($tenant === null) {
            return ApiResponse::notFound('Tenant not found');
        }

        if ($tenant->status === 'pending') {
            return ApiResponse::forbidden('Tenant account is pending activation');
        }

        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $tenant->id,
            ownerId: $tenant->ownerId,
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
