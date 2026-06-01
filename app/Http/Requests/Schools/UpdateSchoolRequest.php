<?php

namespace App\Http\Requests\Schools;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate authorization happens in the controller via $this->authorize()
    }

    /**
     * Partial-update rules. Each field uses `sometimes` so the request only
     * validates and applies the keys that were actually sent. Slug is not
     * editable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            'address' => ['sometimes', 'nullable', 'array'],
            'address.street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.exterior_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address.interior_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address.neighborhood' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.municipality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'address.country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
