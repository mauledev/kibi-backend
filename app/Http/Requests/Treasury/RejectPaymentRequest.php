<?php

namespace App\Http\Requests\Treasury;

use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate authorization happens in the controller via $this->authorize()
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(PaymentRejectReason::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): PaymentRejectReason
    {
        return PaymentRejectReason::from($this->validated('reason'));
    }
}
