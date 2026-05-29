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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_paternal' => ['required', 'string', 'max:100'],
            'last_name_maternal' => ['nullable', 'string', 'max:100'],
        ];
    }
}
