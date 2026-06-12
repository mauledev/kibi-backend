<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Notifications module.
 *
 * Per-user routine notifications are NOT audited (see docs/audit.md); only
 * bulk dispatches and configuration changes reach the audit trail.
 */
enum NotificationsAuditEvent: string implements AuditEvent
{
    case DISPATCH = 'notification.dispatch';
    case CONFIG_UPDATE = 'notification.config_update';
}
