<?php

namespace App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class DenyPermissionFromAssignmentUseCase
{
    private const PROTECTED_SLUGS = ['owner', 'gestor_escuelas'];

    public function __construct(
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Add a permission denial to a specific user_role_assignment.
     * Cannot be applied to owner or gestor_escuelas assignments.
     * Idempotent — does nothing if the denial already exists.
     * Returns true when a new denial was created, false when it already existed.
     *
     * @throws AssignmentNotFoundException
     * @throws SystemRoleViolationException
     * @throws PermissionNotFoundException
     */
    public function execute(DenyPermissionFromAssignmentInput $input): bool
    {
        $assignment = $this->assignments->findByUuid($input->assignmentUuid);

        if ($assignment === null) {
            throw new AssignmentNotFoundException;
        }

        $roleSlug = $this->assignments->findRoleSlugByAssignmentId($assignment->getId());

        if ($roleSlug !== null && in_array($roleSlug, self::PROTECTED_SLUGS, true)) {
            throw new SystemRoleViolationException(
                'Cannot add permission denials to owner or gestor_escuelas assignments.'
            );
        }

        $permission = $this->permissions->findByUuid($input->permissionUuid);

        if ($permission === null) {
            throw new PermissionNotFoundException;
        }

        $created = $this->assignments->addDenial($assignment->getId(), $permission->getId());

        if ($created) {
            $this->audit->log(
                action: 'permission.deny',
                userId: null,
                entityId: $assignment->getId(),
                structAfter: [
                    'assignment_id' => $assignment->getId(),
                    'permission_id' => $permission->getId(),
                    'permission_slug' => $permission->getSlug(),
                ],
            );
        }

        return $created;
    }
}
