<?php

namespace App\Modules\Roles\Domain\Enums;

enum ActorRoleEnum: string
{
    case OWNER = 'owner';
    case GESTOR_ESCUELAS = 'gestor_escuelas';
    case DIRECTOR = 'director';

    /**
     * Return cases in descending authority order for actor resolution.
     *
     * @return array<ActorRoleEnum>
     */
    public static function orderedByAuthority(): array
    {
        return [self::OWNER, self::GESTOR_ESCUELAS, self::DIRECTOR];
    }
}
