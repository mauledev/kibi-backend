<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CreatePersonnelRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `staff.superadmin` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:operator,leader,support'],

            'personal_data' => ['required', 'array'],
            'personal_data.first_name' => ['required', 'string', 'max:100'],
            'personal_data.last_name_paternal' => ['required', 'string', 'max:100'],
            'personal_data.last_name_maternal' => ['nullable', 'string', 'max:100'],
            'personal_data.email' => ['required', 'email', 'max:255'],
            'personal_data.phone' => ['nullable', 'string', 'max:30'],

            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
