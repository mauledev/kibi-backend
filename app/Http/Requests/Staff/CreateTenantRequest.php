<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    /**
     * All staff-authenticated requests are authorized by the route middleware.
     * Fine-grained authorization (e.g. staff role check) belongs in a Policy
     * if needed in the future.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_slug' => ['required', 'string', 'max:100', 'alpha_dash'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_first_name' => ['required', 'string', 'max:100'],
            'owner_last_name_paternal' => ['required', 'string', 'max:100'],
            'owner_last_name_maternal' => ['nullable', 'string', 'max:100'],
        ];
    }
}
