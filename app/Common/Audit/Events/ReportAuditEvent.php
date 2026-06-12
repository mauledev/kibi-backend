<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for report generation and data export.
 */
enum ReportAuditEvent: string implements AuditEvent
{
    case EXPORT = 'report.export';
    case GENERATE = 'report.generate';
}
