<?php

namespace App\Modules\Treasury\Application\UseCases\GetPayment;

final readonly class GetPaymentInput
{
    public function __construct(
        public string $uuid,
    ) {}
}
