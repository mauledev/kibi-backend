<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by PaymentController::ensureStaff() —
        // the route lives under the staff prefix and rejects non-staff
        // tokens before the use case runs.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_received_cents' => ['required', 'integer', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
