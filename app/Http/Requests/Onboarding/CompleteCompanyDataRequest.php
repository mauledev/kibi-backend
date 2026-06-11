<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCompanyDataRequest extends FormRequest
{
    /**
     * Normalise the RFC to uppercase before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('rfc')) {
            $this->merge(['rfc' => mb_strtoupper((string) $this->input('rfc'))]);
        }

        // Default country to 'MX' when absent inside the nested array
        $address = $this->input('fiscal_address', []);

        if (is_array($address) && ! isset($address['country'])) {
            $address['country'] = 'MX';
            $this->merge(['fiscal_address' => $address]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
            'fiscal_address' => ['required', 'array'],
            'fiscal_address.street' => ['nullable', 'string'],
            'fiscal_address.exterior_number' => ['nullable', 'string'],
            'fiscal_address.interior_number' => ['nullable', 'string'],
            'fiscal_address.neighborhood' => ['nullable', 'string'],
            'fiscal_address.municipality' => ['nullable', 'string'],
            'fiscal_address.state' => ['nullable', 'string'],
            'fiscal_address.postal_code' => ['nullable', 'string'],
            'fiscal_address.country' => ['nullable', 'string'],
            'primary_contact_name' => ['required', 'string', 'max:255'],
            'primary_contact_email' => ['required', 'email:rfc'],
            'primary_contact_phone' => ['required', 'string', 'min:10', 'max:30'],
        ];
    }
}
