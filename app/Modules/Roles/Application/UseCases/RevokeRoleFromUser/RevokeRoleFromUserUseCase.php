<?php

namespace App\Modules\Roles\Application\UseCases\RevokeRoleFromUser;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class RevokeRoleFromUserUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Revoke an active role assignment from a user.
     * The actor must have a strictly lower hierarchy_level than the target role.
     *
     * @throws RoleNotFoundException
     * @throws HierarchyViolationException
     * @throws AssignmentNotFoundException
     */
    public function execute(RevokeRoleFromUserInput $input): UserRoleAssignment
    {
        $role = $this->roles->findByPublicId($input->rolePublicId);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only revoke roles with a hierarchy level strictly greater than your own.'
            );
        }

        $assignment = $this->assignments->findActiveByUserAndRole(
            $input->targetUserId,
            $role->getId(),
            $input->schoolId,
        );

        if ($assignment === null) {
            throw new AssignmentNotFoundException;
        }

        $updated = $this->assignments->revoke($assignment->getId());

        $this->audit->log(
            action: 'role.revoke',
            userId: $input->actorUserId,
            entityId: $assignment->getId(),
            structBefore: [
                'user_id' => $input->targetUserId,
                'role_id' => $role->getId(),
                'role_slug' => $role->getSlug(),
                'school_id' => $input->schoolId,
            ],
        );

        return $updated;
    }
}
