<?php

namespace App\Http\Requests\Schools;

use Illuminate\Foundation\Http\FormRequest;

class CreateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate authorization happens in the controller via $this->authorize()
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'phone' => ['nullable', 'string', 'max:30'],

            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.exterior_number' => ['nullable', 'string', 'max:20'],
            'address.interior_number' => ['nullable', 'string', 'max:20'],
            'address.neighborhood' => ['nullable', 'string', 'max:255'],
            'address.municipality' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:10'],
            'address.country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
