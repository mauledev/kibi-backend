<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Dunning module — staged overdue-payment collection.
 */
enum DunningAuditEvent: string implements AuditEvent
{
    case REMINDER_SEND = 'reminder.send';
    case ESCALATION_TRIGGER = 'escalation.trigger';
    case SERVICE_SUSPEND = 'service.suspend';
}
