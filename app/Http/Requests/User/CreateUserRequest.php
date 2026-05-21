<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateUserRequest
 * Validación para crear usuario (admin solamente)
 */
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string|min:3|max:255',
            'school_id' => 'required|string|exists:schools,id',
            'role' => 'required|in:director,teacher,parent,student',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email requerido',
            'email.unique' => 'Email ya registrado',
            'password.min' => 'Mínimo 8 caracteres',
            'name.required' => 'Nombre requerido',
            'school_id.exists' => 'Escuela no existe',
        ];
    }
}
