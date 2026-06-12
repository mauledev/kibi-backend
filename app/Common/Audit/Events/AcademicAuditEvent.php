<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Academic module — grades, terms and attendance.
 */
enum AcademicAuditEvent: string implements AuditEvent
{
    case GRADE_RECORD_CAPTURE = 'grade_record.capture';
    case GRADE_RECORD_UPDATE = 'grade_record.update';
    case TERM_CLOSE = 'term.close';
    case REPORT_CARD_GENERATE = 'report_card.generate';
    case ATTENDANCE_RECORD = 'attendance.record';
}
