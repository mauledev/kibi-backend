<?php

namespace App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit;

class ConfigureCustomRoleLimitInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $tenantId,
        public readonly int $limit,
    ) {}
}
