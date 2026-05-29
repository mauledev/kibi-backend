<?php

declare(strict_types=1);

namespace App\Http\Resources\Staff;

use App\Modules\Tenant\Domain\Entities\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the Tenant domain entity into the API response shape.
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
                'first_name' => $owner->getFirstName(),
                'last_name_paternal' => $owner->getLastNamePaternal(),
                'last_name_maternal' => $owner->getLastNameMaternal(),
                'full_name' => $owner->getFullName(),
            ] : null,
        ];
    }
}
