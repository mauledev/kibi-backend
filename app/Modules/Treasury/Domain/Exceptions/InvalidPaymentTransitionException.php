<?php

namespace App\Modules\Treasury\Domain\Exceptions;

use App\Modules\Treasury\Domain\Enums\PaymentStatus;

/**
 * Thrown when a state transition attempt violates the payment state machine.
 *
 * The HTTP layer maps this exception to a 409 Conflict response — the
 * request was well-formed but the resource is not in a state that allows
 * the requested transition.
 */
final class InvalidPaymentTransitionException extends PaymentException
{
    public static function cannotApprove(PaymentStatus $currentStatus): self
    {
        return new self(
            "Cannot approve a payment in status '{$currentStatus->value}'. Only pending payments can be approved."
        );
    }

    public static function cannotReject(PaymentStatus $currentStatus): self
    {
        return new self(
            "Cannot reject a payment in status '{$currentStatus->value}'. Only pending payments can be rejected."
        );
    }
}
