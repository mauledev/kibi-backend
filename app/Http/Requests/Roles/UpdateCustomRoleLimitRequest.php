<?php

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomRoleLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
