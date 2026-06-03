<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Treasury module — charges, reconciliation, refunds.
 */
enum TreasuryAuditEvent: string implements AuditEvent
{
    case CHARGE_CREATE = 'charge.create';
    case CHARGE_UPDATE = 'charge.update';
    case PAYMENT_RECONCILE = 'payment.reconcile';
    case REFUND_ISSUE = 'refund.issue';
}
