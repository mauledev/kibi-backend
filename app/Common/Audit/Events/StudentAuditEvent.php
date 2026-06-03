<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for student records — enrollment and deactivation.
 */
enum StudentAuditEvent: string implements AuditEvent
{
    case ENROLL = 'student.enroll';
    case DEACTIVATE = 'student.deactivate';
    case REACTIVATE = 'student.reactivate';
}
