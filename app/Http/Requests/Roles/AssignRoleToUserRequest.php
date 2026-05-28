<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleToUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_uuid'   => ['required', 'string', 'uuid'],
            'school_uuid' => ['nullable', 'string', 'uuid', 'exists:schools,uuid'],
        ];
    }
}
