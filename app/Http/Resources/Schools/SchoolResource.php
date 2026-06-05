<?php

namespace App\Http\Resources\Schools;

use App\Modules\Schools\Domain\Entities\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var School $school */
        $school = $this->resource;

        return [
            'uuid' => $school->getUuid(),
            'name' => $school->getName(),
            'slug' => $school->getSlug(),
            'phone' => $school->getPhone(),
            'address' => $this->formatAddress($school->getAddress()),
            'status' => $school->getStatus(),
            'created_at' => $school->getCreatedAt()?->format('c'),
            'updated_at' => $school->getUpdatedAt()?->format('c'),
            'deleted_at' => $school->getDeletedAt()?->format('c'),
        ];
    }

    /**
     * Normalise the address JSONB column into a predictable shape.
     *
     * @param  array<string, mixed>|null  $address
     * @return array<string, string|null>
     */
    private function formatAddress(?array $address): array
    {
        $addr = $address ?? [];

        return [
            'street' => $addr['street'] ?? null,
            'exterior_number' => $addr['exterior_number'] ?? null,
            'interior_number' => $addr['interior_number'] ?? null,
            'neighborhood' => $addr['neighborhood'] ?? null,
            'municipality' => $addr['municipality'] ?? null,
            'state' => $addr['state'] ?? null,
            'postal_code' => $addr['postal_code'] ?? null,
            'country' => $addr['country'] ?? 'MX',
        ];
    }
}
