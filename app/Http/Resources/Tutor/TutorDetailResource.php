<?php

namespace App\Http\Resources\Tutor;

use App\Modules\Tutor\Domain\Entities\Tutor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full tutor representation used in single-resource responses.
 *
 * @mixin Tutor
 */
class TutorDetailResource extends JsonResource
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
            'first_name' => $tutor->getFirstName(),
            'last_name_paternal' => $tutor->getLastNamePaternal(),
            'last_name_maternal' => $tutor->getLastNameMaternal(),
            'email' => $tutor->getEmail(),
            'phone' => $tutor->getPhone(),
            'status' => $tutor->getStatus(),
            'occupation' => $tutor->getOccupation(),
            'created_at' => $tutor->getCreatedAt()->format('c'),
        ];
    }
}
