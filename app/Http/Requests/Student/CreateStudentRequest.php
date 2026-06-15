<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request body for POST /students (create student).
 *
 * Gate authorization is handled in the controller via $this->authorize('user.create').
 * This request only ensures the user is authenticated before running rules.
 */
class CreateStudentRequest extends FormRequest
{
    /** Allow any authenticated user; gate check happens in the controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validation rules for creating a student.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_paternal' => ['required', 'string', 'max:100'],
            'last_name_maternal' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'enrollment_number' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'group_uuid' => ['nullable', 'uuid', 'exists:groups,uuid'],
        ];
    }
}
