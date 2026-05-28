<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;

class RevokeRoleFromUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'school_uuid' => ['nullable', 'string', 'uuid', 'exists:schools,uuid'],
        ];
    }
}
