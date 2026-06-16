<?php

namespace App\Modules\Treasury\Domain\Enums;

/**
 * Stable identifier of why a payment was rejected.
 *
 * Stored as a string column on `payment_state_transitions.reason`. Stays in
 * English on the wire; the frontend maps each case to its translated label.
 */
enum PaymentRejectReason: string
{
    case AmountMismatch = 'amount_mismatch';
    case InvalidReference = 'invalid_reference';
    case IllegibleReceipt = 'illegible_receipt';
    case TransferNotFound = 'transfer_not_found';
    case Other = 'other';
}
