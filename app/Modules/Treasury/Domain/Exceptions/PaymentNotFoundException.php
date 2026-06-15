<?php

namespace App\Modules\Treasury\Domain\Exceptions;

final class PaymentNotFoundException extends PaymentException
{
    public static function withUuid(string $uuid): self
    {
        return new self("Payment with uuid '{$uuid}' not found.");
    }
}
