<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Merge the {provider} route parameter into the request data
     * so it participates in validation alongside access_token.
     */
    protected function prepareForValidation(): void
    {
        $provider = $this->route('provider');

        if (is_string($provider)) {
            $this->merge(['provider' => $provider]);
        }
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'access_token' => 'required|string',
            'provider' => 'required|string|in:google,microsoft',
        ];
    }
}
