<?php

namespace App\Modules\Treasury\Domain\Enums;

/**
 * Event recorded on `payment_state_transitions.event` for every entry of
 * the payment's state log.
 *
 * Distinct from {@see PaymentStatus} because the same target status can be
 * reached through different events (e.g. `Approved` is reachable only via
 * the `Approved` event, but `Pending` may be reached via both `Created`
 * and a future `ObservationResubmitted` event).
 */
enum PaymentStateEvent: string
{
    case Created = 'created';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ObservationRequested = 'observation_requested';
}
