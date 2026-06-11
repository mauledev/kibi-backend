<?php

namespace App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment;

class DenyPermissionFromAssignmentInput
{
    public function __construct(
        public readonly string $actorSlug,
        public readonly string $assignmentUuid,
        public readonly string $permissionUuid,
        public readonly ?int $actorUserId = null,
    ) {}
}
