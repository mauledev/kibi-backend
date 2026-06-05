<?php

namespace App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;

class RestorePermissionToAssignmentUseCase
{
    public function __construct(
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Remove a permission denial from a specific user_role_assignment.
     * Idempotent — does nothing if the denial does not exist.
     *
     * @throws AssignmentNotFoundException
     * @throws PermissionNotFoundException
     */
    public function execute(RestorePermissionToAssignmentInput $input): void
    {
        $assignment = $this->assignments->findByUuid($input->assignmentUuid);

        if ($assignment === null) {
            throw new AssignmentNotFoundException;
        }

        $permission = $this->permissions->findByUuid($input->permissionUuid);

        if ($permission === null) {
            throw new PermissionNotFoundException;
        }

        $this->assignments->removeDenial($assignment->getId(), $permission->getId());

        $this->audit->log(
            action: 'permission.restore',
            userId: null,
            entityId: $assignment->getId(),
            structAfter: [
                'assignment_id' => $assignment->getId(),
                'permission_id' => $permission->getId(),
                'permission_slug' => $permission->getSlug(),
            ],
        );
    }
}
