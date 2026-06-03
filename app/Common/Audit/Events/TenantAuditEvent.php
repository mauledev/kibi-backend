<?php

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Tenant module — tenant lifecycle and branding.
 */
enum TenantAuditEvent: string implements AuditEvent
{
    case CREATE = 'tenant.create';
    case SUSPEND = 'tenant.suspend';
    case REACTIVATE = 'tenant.reactivate';
    case BRANDING_CHANGE = 'tenant.branding_change';
}
