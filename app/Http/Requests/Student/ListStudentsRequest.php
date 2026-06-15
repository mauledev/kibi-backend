<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query parameters for GET /students (list students).
 *
 * Gate authorization is handled in the controller via $this->authorize('user.view').
 */
class ListStudentsRequest extends FormRequest
{
    /** Allow any authenticated user; gate check happens in the controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validation rules for listing students.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
