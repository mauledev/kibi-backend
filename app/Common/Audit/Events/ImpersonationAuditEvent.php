<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for Superadmin impersonation (E-22).
 *
 * Every action performed while impersonating must be audited; ACTION is the
 * catch-all marker emitted alongside the impersonated operation.
 */
enum ImpersonationAuditEvent: string implements AuditEvent
{
    case START = 'impersonation.start';
    case END = 'impersonation.end';
    case ACTION = 'impersonation.action';
}
