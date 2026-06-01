<?php

namespace App\Modules\Tenant\Application\UseCases\GetTenantInfo;

use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;

class GetTenantInfoUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Look up a tenant by its URL slug.
     *
     * Returns the Tenant entity when found, or null when no tenant matches the slug.
     * No business logic beyond the lookup — status validation is the caller's concern.
     */
    public function execute(string $slug): ?Tenant
    {
        return $this->tenants->findBySlug($slug);
    }
}
