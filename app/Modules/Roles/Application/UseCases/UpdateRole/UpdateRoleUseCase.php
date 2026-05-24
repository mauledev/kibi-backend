<?php

namespace App\Modules\Roles\Application\UseCases\UpdateRole;

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class UpdateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Update mutable fields on a role.
     * The actor must have a strictly lower hierarchy_level than the target role.
     *
     * @throws RoleNotFoundException
     * @throws HierarchyViolationException
     */
    public function execute(UpdateRoleInput $input): Role
    {
        $role = $this->roles->findByPublicId($input->publicId);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only update roles with a hierarchy level strictly greater than your own.'
            );
        }

        $before = $this->roleToArray($role);

        $role->rename($input->name);
        $updated = $this->roles->update($role);

        $this->audit->log(
            action: 'role.update',
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structBefore: $before,
            structAfter: $this->roleToArray($updated),
        );

        return $updated;
    }

    /** @return array<string, mixed> */
    private function roleToArray(Role $role): array
    {
        return [
            'id' => $role->getId(),
            'public_id' => $role->getPublicId(),
            'name' => $role->getName(),
            'hierarchy_level' => $role->getHierarchyLevel(),
        ];
    }
}
