<?php

namespace App\Modules\Treasury\Domain\Enums;

/**
 * Lifecycle state of a Payment.
 *
 * `Pending` is the initial state set by the system when the payment record
 * is created. `Approved` and `Rejected` are terminal from the frontend's
 * perspective. `WithObservation` is reserved for the post-MVP observation
 * flow and is not yet emitted by any UseCase.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case WithObservation = 'with_observation';
}
