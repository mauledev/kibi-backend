<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Roles module — role and permission management.
 */
enum RoleAuditEvent: string implements AuditEvent
{
    case ROLE_CREATE = 'role.create';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';
    case ROLE_ASSIGN = 'role.assign';
    case ROLE_REVOKE = 'role.revoke';
    case PERMISSION_GRANT = 'permission.grant';
    case PERMISSION_REVOKE = 'permission.revoke';
}
