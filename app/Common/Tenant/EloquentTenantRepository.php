<?php

namespace App\Common\Tenant;

use App\Models\Tenant;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?TenantData
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if ($tenant === null) {
            return null;
        }

        return new TenantData(id: $tenant->id);
    }
}
