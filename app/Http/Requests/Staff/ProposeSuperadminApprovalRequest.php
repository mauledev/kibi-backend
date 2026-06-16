<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload to propose a superadmin creation (dual-control, step 1).
 * `personal_data` mirrors the personnel-creation envelope so frontend mappers
 * stay uniform across staff wizards.
 *
 * Authorization is enforced by the `staff.superadmin` middleware on the route group.
 */
class ProposeSuperadminApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:1000'],

            'personal_data' => ['required', 'array'],
            'personal_data.first_name' => ['required', 'string', 'max:100'],
            'personal_data.last_name_paternal' => ['required', 'string', 'max:100'],
            'personal_data.last_name_maternal' => ['nullable', 'string', 'max:100'],
            'personal_data.email' => ['required', 'email', 'max:255'],
            'personal_data.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
