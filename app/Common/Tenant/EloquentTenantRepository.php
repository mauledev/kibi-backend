<?php

namespace App\Common\Tenant;

use App\Models\Tenant;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?TenantData
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            return null;
        }

        return new TenantData(id: $tenant->id, status: $tenant->status, ownerId: $tenant->owner_id);
    }
}
