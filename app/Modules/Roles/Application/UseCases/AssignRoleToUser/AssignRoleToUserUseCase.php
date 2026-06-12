<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\AssignRoleToUser;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Audit\Events\RoleAuditEvent;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Enums\RoleExclusionEnum;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
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
     * Only owner, school_manager, and director can assign roles.
     * If the user already holds an active assignment for this role+school, it is returned unchanged.
     *
     * @throws UserNotFoundException
     * @throws RoleNotFoundException
     * @throws OwnerRoleAssignmentException
     * @throws HierarchyViolationException
     * @throws RoleExclusionException
     */
    public function execute(AssignRoleToUserInput $input): UserRoleAssignment
    {
        if (! in_array($input->actorSlug, ['owner', 'school_manager', 'director'], true)) {
            throw new HierarchyViolationException(
                'Only owner, school_manager, or director can assign roles to users.'
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

        if ($role->getSlug() === 'owner') {
            throw new OwnerRoleAssignmentException;
        }

        // Director cannot assign school_manager role
        if ($input->actorSlug === 'director' && $role->getSlug() === 'school_manager') {
            throw new HierarchyViolationException(
                'Director cannot assign the school_manager role.'
            );
        }

        $schoolId = $input->schoolUuid !== null
            ? $this->schools->findIdByUuid($input->schoolUuid)
            : null;

        // Mutual exclusion check for school-scoped assignments
        if ($schoolId !== null) {
            $incompatible = RoleExclusionEnum::getIncompatible($role->getSlug());

            if ($incompatible !== []) {
                $existingSlugs = $this->assignments->findActiveRoleSlugsForUserInSchool(
                    $targetUser->getId(),
                    $schoolId,
                );

                foreach ($existingSlugs as $existingSlug) {
                    if (in_array($existingSlug, $incompatible, true)) {
                        throw new RoleExclusionException(
                            "Cannot assign '{$role->getSlug()}' because the user already holds '{$existingSlug}' in this school."
                        );
                    }
                }
            }
        }

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
            action: RoleAuditEvent::ROLE_ASSIGN,
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
