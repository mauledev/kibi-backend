<?php

namespace App\Modules\Roles\Application\UseCases\RevokePermissionFromRole;

class RevokePermissionFromRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly bool $actorCanManagePermissions,
        public readonly string $roleUuid,
        public readonly string $permissionUuid,
    ) {}
}
