<?php

namespace App\Modules\Roles\Application\UseCases\RevokePermissionFromRole;

class RevokePermissionFromRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly string $actorSlug,
        public readonly string $roleUuid,
        public readonly string $permissionUuid,
    ) {}
}
