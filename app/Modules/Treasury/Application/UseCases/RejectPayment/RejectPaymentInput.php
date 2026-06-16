<?php

namespace App\Modules\Treasury\Application\UseCases\RejectPayment;

use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;

final readonly class RejectPaymentInput
{
    public function __construct(
        public string $uuid,
        public int $actorUserId,
        public string $actorName,
        public PaymentRejectReason $reason,
        public ?string $note,
    ) {}
}
