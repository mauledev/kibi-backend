<?php

namespace App\Modules\Tenant\Application\UseCases\UpdateTenant;

use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;
use App\Modules\Tenant\Domain\Exceptions\TenantNotFoundException;
use App\Modules\Tenant\Domain\Exceptions\TenantSlugAlreadyExistsException;

class UpdateTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Update a tenant's mutable fields.
     *
     * @throws TenantNotFoundException When no tenant exists for the given UUID.
     * @throws TenantSlugAlreadyExistsException When the new slug is already taken by another tenant.
     */
    public function execute(UpdateTenantInput $input): Tenant
    {
        $tenant = $this->tenants->findByUuid($input->uuid);

        if ($tenant === null) {
            throw new TenantNotFoundException($input->uuid);
        }

        $existing = $this->tenants->findBySlug($input->slug);

        if ($existing !== null && $existing->getId() !== $tenant->getId()) {
            throw new TenantSlugAlreadyExistsException($input->slug);
        }

        return $this->tenants->update($tenant->getId(), $input->name, $input->slug, $input->status);
    }
}
