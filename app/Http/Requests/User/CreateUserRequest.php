<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateUserRequest
 * Validation for user creation.
 */
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Invite a user: minimal identity + at least one role/school assignment.
     * No password — the invitee sets it when activating via the magic link.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_paternal' => ['required', 'string', 'max:100'],
            'last_name_maternal' => ['nullable', 'string', 'max:100'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.role_uuid' => ['required', 'string', 'uuid'],
            'assignments.*.school_uuid' => ['nullable', 'string', 'uuid', 'exists:schools,uuid'],
        ];
    }
}
