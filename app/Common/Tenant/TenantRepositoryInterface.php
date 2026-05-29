<?php

namespace App\Common\Tenant;

interface TenantRepositoryInterface
{
    /**
     * Find a tenant by its URL slug regardless of status.
     * Returns null when the tenant does not exist or is soft-deleted.
     */
    public function findBySlug(string $slug): ?TenantData;
}
