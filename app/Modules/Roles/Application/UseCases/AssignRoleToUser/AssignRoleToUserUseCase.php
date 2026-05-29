<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\AssignRoleToUser;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class AssignRoleToUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly SchoolRepositoryInterface $schools,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Assign a role to a user.
     * The actor can only assign roles with a hierarchy_level strictly greater than their own.
     * If the user already holds an active assignment for this role, a new one is NOT created.
     *
     * @throws UserNotFoundException
     * @throws RoleNotFoundException
     * @throws OwnerRoleAssignmentException
     * @throws HierarchyViolationException
     */
    public function execute(AssignRoleToUserInput $input): UserRoleAssignment
    {
        $actor = $this->users->findByUuid($input->actorUuid);

        $targetUser = $this->users->findByUuid($input->targetUserUuid);

        if ($targetUser === null) {
            throw new UserNotFoundException;
        }

        $role = $this->roles->findByUuid($input->roleUuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->getSlug() === 'owner') {
            throw new OwnerRoleAssignmentException;
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only assign roles with a hierarchy level strictly greater than your own.'
            );
        }

        $schoolId = $input->schoolUuid !== null
            ? $this->schools->findIdByUuid($input->schoolUuid)
            : null;

        $existing = $this->assignments->findActiveByUserAndRole(
            $targetUser->getId(),
            $role->getId(),
            $schoolId,
        );

        if ($existing !== null) {
            return $existing;
        }

        $assignment = $this->assignments->create(
            userId: $targetUser->getId(),
            roleId: $role->getId(),
            schoolId: $schoolId,
            assignedBy: $actor?->getId(),
        );

        $this->audit->log(
            action: 'role.assign',
            userId: $actor?->getId(),
            entityId: $assignment->getId(),
            structAfter: [
                'user_id' => $targetUser->getId(),
                'role_id' => $role->getId(),
                'role_slug' => $role->getSlug(),
                'school_id' => $schoolId,
            ],
        );

        return $assignment;
    }
}
