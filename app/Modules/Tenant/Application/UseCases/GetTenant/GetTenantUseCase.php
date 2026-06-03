<?php

namespace App\Modules\Tenant\Application\UseCases\GetTenant;

use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;
use App\Modules\Tenant\Domain\Exceptions\TenantNotFoundException;

class GetTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Find a tenant by UUID with its owner embedded.
     *
     * @throws TenantNotFoundException When no tenant exists for the given UUID.
     */
    public function execute(string $uuid): Tenant
    {
        $tenant = $this->tenants->findByUuidWithOwner($uuid);

        if ($tenant === null) {
            throw new TenantNotFoundException($uuid);
        }

        return $tenant;
    }
}
