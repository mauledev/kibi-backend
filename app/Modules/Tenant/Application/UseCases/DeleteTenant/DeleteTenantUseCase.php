<?php

namespace App\Modules\Tenant\Application\UseCases\DeleteTenant;

use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Exceptions\TenantNotFoundException;

class DeleteTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Soft-delete a tenant by UUID.
     *
     * @throws TenantNotFoundException When no tenant exists for the given UUID.
     */
    public function execute(string $uuid): void
    {
        $tenant = $this->tenants->findByUuid($uuid);

        if ($tenant === null) {
            throw new TenantNotFoundException($uuid);
        }

        $this->tenants->delete($tenant->getId());
    }
}
