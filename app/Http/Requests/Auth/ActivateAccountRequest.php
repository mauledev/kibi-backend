<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ActivateAccountRequest extends FormRequest
{
    /**
     * This route is public — no authentication is required.
     * The signed URL signature is validated in the controller before calling the UseCase.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
