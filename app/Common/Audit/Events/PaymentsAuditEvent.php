<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Payments module — Mercado Pago checkout outcomes.
 */
enum PaymentsAuditEvent: string implements AuditEvent
{
    case PAYMENT_INITIATE = 'payment.initiate';
    case PAYMENT_APPROVE = 'payment.approve';
    case PAYMENT_REJECT = 'payment.reject';
}
