<?php

namespace App\Http\Requests\Schools;

use Illuminate\Foundation\Http\FormRequest;

class CreateSchoolRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate authorization happens in the controller via $this->authorize()
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
