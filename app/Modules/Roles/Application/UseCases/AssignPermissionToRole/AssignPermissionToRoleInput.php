<?php

namespace App\Modules\Roles\Application\UseCases\AssignPermissionToRole;

class AssignPermissionToRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly string $actorSlug,
        public readonly string $roleUuid,
        public readonly string $permissionUuid,
        /** Internal school id for gestor/director scope check. Null for owner. */
        public readonly ?int $schoolId = null,
    ) {}
}
