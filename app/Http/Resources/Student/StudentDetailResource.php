<?php

namespace App\Http\Resources\Student;

use App\Modules\Student\Domain\Entities\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Domain Student entity for the single-student detail response.
 *
 * Includes all fields from StudentListResource plus individual name components,
 * birth_date, national_id, gender, and blood_type — fields required by official
 * school documents (boletas, constancias).
 *
 * @mixin Student
 */
class StudentDetailResource extends JsonResource
{
    /**
     * Transform the Domain Student entity into the full detail API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Student $student */
        $student = $this->resource;

        return [
            'uuid' => $student->getUserUuid(),
            'first_name' => $student->getFirstName(),
            'last_name_paternal' => $student->getLastNamePaternal(),
            'last_name_maternal' => $student->getLastNameMaternal(),
            'full_name' => $student->getFullName(),
            'email' => $student->getEmail(),
            'phone' => $student->getPhone(),
            'status' => $student->getStatus(),
            'birth_date' => $student->getBirthDate(),
            'national_id' => $student->getNationalId(),
            'enrollment_number' => $student->getEnrollmentNumber(),
            'gender' => $student->getGender(),
            'blood_type' => $student->getBloodType(),
            'group' => $student->getGroupUuid() !== null
                ? ['uuid' => $student->getGroupUuid(), 'name' => $student->getGroupName()]
                : null,
            'created_at' => $student->getCreatedAt()->format('c'),
        ];
    }
}
