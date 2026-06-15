<?php

namespace App\Modules\Roles\Domain\Enums;

enum ActorRoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case OWNER = 'owner';
    case SCHOOL_MANAGER = 'school_manager';
    case DIRECTOR = 'director';

    /**
     * Return cases in descending authority order for actor resolution.
     *
     * @return array<ActorRoleEnum>
     */
    public static function orderedByAuthority(): array
    {
        return [self::SUPERADMIN, self::OWNER, self::SCHOOL_MANAGER, self::DIRECTOR];
    }
}
