<?php

namespace App\Http\Resources\Staff;

use App\Modules\Tenant\Domain\Entities\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantListResource extends JsonResource
{
    /**
     * Transform the Tenant domain entity into the compact list API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;
        $owner = $tenant->getOwner();

        return [
            'uuid' => $tenant->getUuid(),
            'name' => $tenant->getName(),
            'slug' => $tenant->getSlug(),
            'status' => $tenant->getStatus(),
            'owner' => $owner !== null ? [
                'uuid' => $owner->getUuid(),
                'email' => $owner->getEmail(),
                'full_name' => $owner->getFullName(),
            ] : null,
            'created_at' => $tenant->getCreatedAt(),
        ];
    }
}
