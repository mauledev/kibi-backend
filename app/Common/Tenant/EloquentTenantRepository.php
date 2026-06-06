<?php

namespace App\Common\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Str;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?TenantData
    {
        $tenant = Tenant::where('slug', Str::lower($slug))->first();

        if ($tenant === null) {
            return null;
        }

        return new TenantData(id: $tenant->id, status: $tenant->status, ownerId: $tenant->owner_id);
    }
}
