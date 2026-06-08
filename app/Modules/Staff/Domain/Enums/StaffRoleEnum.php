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

    /**
     * Whether the role requires TOTP 2FA. Derived server-side from the role —
     * never trusted from the request (ADR-004).
     */
    public function requires2fa(): bool
    {
        return match ($this) {
            self::OPERATOR => false,
            self::LEADER, self::SUPPORT => true,
        };
    }
}
