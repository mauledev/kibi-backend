<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Treasury module — charges, reconciliation, refunds.
 */
enum TreasuryAuditEvent: string implements AuditEvent
{
    case CHARGE_CREATE = 'charge.create';
    case CHARGE_UPDATE = 'charge.update';
    case RECONCILIATION_EXECUTE = 'reconciliation.execute';
    case REFUND_ISSUE = 'refund.issue';
}
