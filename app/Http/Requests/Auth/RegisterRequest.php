<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RegisterRequest
 * Validación para registro de usuarios
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Registro es público
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'required|string|min:3|max:255',
            'school_uuid' => 'required|string|uuid|exists:schools,uuid',
            'role' => 'required|in:director,teacher,parent,student',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'El email ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'name.required' => 'El nombre es requerido',
            'school_uuid.required' => 'La escuela es requerida',
            'school_uuid.exists' => 'La escuela seleccionada no existe',
            'role.required' => 'El rol es requerido',
            'role.in' => 'El rol seleccionado es inválido',
        ];
    }
}
