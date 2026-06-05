<?php

namespace App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment;

class RestorePermissionToAssignmentInput
{
    public function __construct(
        public readonly string $assignmentUuid,
        public readonly string $permissionUuid,
        public readonly ?int $actorUserId = null,
    ) {}
}
