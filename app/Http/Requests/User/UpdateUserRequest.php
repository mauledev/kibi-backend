<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateUserRequest
 * Validación para actualizar usuario
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'email' => 'sometimes|email|unique:users,email,'.$userId,
            'name' => 'sometimes|string|min:3|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:director,teacher,parent,student',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
