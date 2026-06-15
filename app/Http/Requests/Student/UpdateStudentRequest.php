<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request body for PUT /students/{uuid} (update student).
 *
 * All fields are optional (sometimes) — only provided fields are updated.
 * Gate authorization is handled in the controller via $this->authorize('user.update').
 */
class UpdateStudentRequest extends FormRequest
{
    /** Allow any authenticated user; gate check happens in the controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validation rules for updating a student.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name_paternal' => ['sometimes', 'string', 'max:100'],
            'last_name_maternal' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'enrollment_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:5'],
            'group_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:groups,uuid'],
        ];
    }
}
