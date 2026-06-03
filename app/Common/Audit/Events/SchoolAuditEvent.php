<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Schools module — school lifecycle and branding.
 */
enum SchoolAuditEvent: string implements AuditEvent
{
    case CREATE = 'school.create';
    case UPDATE = 'school.update';
    case DEACTIVATE = 'school.deactivate';
    case REACTIVATE = 'school.reactivate';
    case BRANDING_CHANGE = 'school.branding_change';
}
