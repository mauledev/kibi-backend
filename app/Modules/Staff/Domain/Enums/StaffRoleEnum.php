<?php

namespace App\Modules\Staff\Domain\Enums;

/**
 * Softlinkia Backoffice operational roles (users.is_staff = true).
 *
 * Mirrors the frontend `BackofficeRole` union. Superadmin is intentionally
 * absent: it is not created through the personnel flow and its authority is
 * handled by an explicit superadmin check on staff routes, not by permissions.
 */
enum StaffRoleEnum: string
{
    case OPERATOR = 'operator';
    case LEADER = 'leader';
    case SUPPORT = 'support';
}
