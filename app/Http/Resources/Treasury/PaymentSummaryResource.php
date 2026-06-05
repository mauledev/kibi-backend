<?php

namespace App\Http\Resources\Treasury;

use App\Modules\Treasury\Domain\Entities\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'uuid' => $payment->getUuid(),
            'status' => $payment->getStatus()->value,
            'company_name' => $payment->getCompanyName(),
            'school_name' => $payment->getSchoolName(),
            'payer_name' => $payment->getPayerName(),
            'amount_cents' => $payment->getAmountCents(),
            'currency' => $payment->getCurrency(),
            'paid_at' => $payment->getPaidAt()?->format('c'),
            'created_at' => $payment->getCreatedAt()?->format('c'),
            'updated_at' => $payment->getUpdatedAt()->format('c'),
        ];
    }
}
