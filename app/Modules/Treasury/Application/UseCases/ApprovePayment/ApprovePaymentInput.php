<?php

namespace App\Modules\Treasury\Application\UseCases\ApprovePayment;

final readonly class ApprovePaymentInput
{
    public function __construct(
        public string $uuid,
        public int $actorUserId,
        public string $actorName,
        public int $receivedAmountCents,
        public ?string $note,
    ) {}
}
