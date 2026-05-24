<?php

namespace App\Modules\Roles\Application\UseCases\AssignRoleToUser;

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class AssignRoleToUserUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Assign a role to a user.
     * The actor can only assign roles with a hierarchy_level strictly greater than their own.
     * If the user already holds an active assignment for this role, a new one is NOT created.
     *
     * @throws RoleNotFoundException
     * @throws HierarchyViolationException
     */
    public function execute(AssignRoleToUserInput $input): UserRoleAssignment
    {
        $role = $this->roles->findByPublicId($input->rolePublicId);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only assign roles with a hierarchy level strictly greater than your own.'
            );
        }

        // Idempotency: return existing active assignment if present
        $existing = $this->assignments->findActiveByUserAndRole(
            $input->targetUserId,
            $role->getId(),
            $input->schoolId,
        );

        if ($existing !== null) {
            return $existing;
        }

        $assignment = $this->assignments->create(
            userId: $input->targetUserId,
            roleId: $role->getId(),
            schoolId: $input->schoolId,
            assignedBy: $input->actorUserId,
        );

        $this->audit->log(
            action: 'role.assign',
            userId: $input->actorUserId,
            entityId: $assignment->getId(),
            structAfter: [
                'user_id' => $input->targetUserId,
                'role_id' => $role->getId(),
                'role_slug' => $role->getSlug(),
                'school_id' => $input->schoolId,
            ],
        );

        return $assignment;
    }
}
