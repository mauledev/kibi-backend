<?php

namespace App\Http\Resources\Tutor;

use App\Modules\Tutor\Domain\Entities\Tutor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact tutor representation used in paginated list responses.
 *
 * @mixin Tutor
 */
class TutorListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tutor $tutor */
        $tutor = $this->resource;

        return [
            'uuid' => $tutor->getUserUuid(),
            'full_name' => $tutor->getFullName(),
            'email' => $tutor->getEmail(),
            'phone' => $tutor->getPhone(),
            'status' => $tutor->getStatus(),
            'occupation' => $tutor->getOccupation(),
            'created_at' => $tutor->getCreatedAt()->format('c'),
        ];
    }
}
