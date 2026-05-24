<?php

namespace App\Modules\Roles\Application\UseCases\DeleteRole;

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class DeleteRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Soft-delete a role.
     * System roles cannot be deleted.
     * The actor must have a strictly lower hierarchy_level than the target role.
     *
     * @throws RoleNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(DeleteRoleInput $input): void
    {
        $role = $this->roles->findByPublicId($input->publicId);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->isSystemRole()) {
            throw new SystemRoleViolationException('System roles cannot be deleted.');
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only delete roles with a hierarchy level strictly greater than your own.'
            );
        }

        $this->roles->delete($input->publicId);

        $this->audit->log(
            action: 'role.delete',
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structBefore: [
                'id' => $role->getId(),
                'public_id' => $role->getPublicId(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
            ],
        );
    }
}
