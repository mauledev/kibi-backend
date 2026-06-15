<?php

namespace App\Http\Resources\Student;

use App\Modules\Student\Domain\Entities\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Domain Student entity for the paginated list response.
 *
 * Exposes only the fields needed for a compact list view. Full detail
 * (individual name components, birth_date, etc.) is available from
 * StudentDetailResource.
 *
 * @mixin Student
 */
class StudentListResource extends JsonResource
{
    /**
     * Transform the Domain Student entity into the list API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Student $student */
        $student = $this->resource;

        return [
            'uuid' => $student->getUserUuid(),
            'full_name' => $student->getFullName(),
            'email' => $student->getEmail(),
            'status' => $student->getStatus(),
            'enrollment_number' => $student->getEnrollmentNumber(),
            'group' => $student->getGroupUuid() !== null
                ? ['uuid' => $student->getGroupUuid(), 'name' => $student->getGroupName()]
                : null,
            'created_at' => $student->getCreatedAt()->format('c'),
        ];
    }
}
