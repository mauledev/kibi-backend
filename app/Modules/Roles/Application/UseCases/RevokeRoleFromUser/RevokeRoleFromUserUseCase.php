<?php

namespace App\Modules\Roles\Application\UseCases\RevokeRoleFromUser;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class RevokeRoleFromUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly SchoolRepositoryInterface $schools,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Revoke an active role assignment from a user.
     * The actor must have a strictly lower hierarchy_level than the target role.
     *
     * @throws UserNotFoundException
     * @throws RoleNotFoundException
     * @throws HierarchyViolationException
     * @throws AssignmentNotFoundException
     */
    public function execute(RevokeRoleFromUserInput $input): UserRoleAssignment
    {
        if (! in_array($input->actorSlug, ['owner', 'school_manager', 'director'], true)) {
            throw new HierarchyViolationException(
                'Only owner, school_manager, or director can revoke roles from users.'
            );
        }

        $actor = $this->users->findByUuid($input->actorUuid);

        $targetUser = $this->users->findByUuid($input->targetUserUuid);

        if ($targetUser === null) {
            throw new UserNotFoundException;
        }

        $role = $this->roles->findByUuid($input->roleUuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        $schoolId = $input->schoolUuid !== null
            ? $this->schools->findIdByUuid($input->schoolUuid)
            : null;

        $assignment = $this->assignments->findActiveByUserAndRole(
            $targetUser->getId(),
            $role->getId(),
            $schoolId,
        );

        if ($assignment === null) {
            throw new AssignmentNotFoundException;
        }

        $updated = $this->assignments->revoke($assignment->getId());

        $this->audit->log(
            action: 'role.revoke',
            userId: $actor?->getId(),
            entityId: $assignment->getId(),
            structBefore: [
                'user_id' => $targetUser->getId(),
                'role_id' => $role->getId(),
                'role_slug' => $role->getSlug(),
                'school_id' => $schoolId,
            ],
        );

        return $updated;
    }
}
