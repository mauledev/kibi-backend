<?php

namespace App\Modules\Roles\Application\UseCases\AssignPermissionToRole;

class AssignPermissionToRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        /** Whether the actor holds manage.permissions */
        public readonly bool $actorCanManagePermissions,
        public readonly string $rolePublicId,
        public readonly string $permissionPublicId,
    ) {}
}
