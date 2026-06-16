<?php

namespace App\Http\Resources\Staff;

use App\Modules\Staff\Domain\Entities\StaffPersonnelListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffPersonnelListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StaffPersonnelListItem $item */
        $item = $this->resource;

        return [
            'uuid' => $item->getUuid(),
            'first_name' => $item->getFirstName(),
            'last_name_paternal' => $item->getLastNamePaternal(),
            'last_name_maternal' => $item->getLastNameMaternal(),
            'full_name' => $item->getFullName(),
            'email' => $item->getEmail(),
            'role' => $item->getRoleSlug() !== null
                ? ['slug' => $item->getRoleSlug(), 'name' => $item->getRoleName()]
                : null,
            'status' => $item->getStatus(),
            'created_at' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
