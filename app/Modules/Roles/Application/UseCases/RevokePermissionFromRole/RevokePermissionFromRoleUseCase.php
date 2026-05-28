<?php

namespace App\Modules\Roles\Application\UseCases\RevokePermissionFromRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Auth\Access\AuthorizationException;

class RevokePermissionFromRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Revoke a permission from a role.
     *
     * @throws AuthorizationException
     * @throws RoleNotFoundException
     * @throws PermissionNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(RevokePermissionFromRoleInput $input): void
    {
        if (! $input->actorCanManagePermissions) {
            throw new AuthorizationException('Actor does not hold the manage.permissions permission.');
        }

        $role = $this->roles->findByUuid($input->roleUuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->isSystemRole()) {
            throw new SystemRoleViolationException;
        }

        if ($role->getHierarchyLevel() <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only manage permissions for roles with a hierarchy level strictly greater than your own.'
            );
        }

        $permission = $this->permissions->findByUuid($input->permissionUuid);

        if ($permission === null) {
            throw new PermissionNotFoundException;
        }

        $this->roles->detachPermission($role->getId(), $permission->getId());

        $this->audit->log(
            action: 'permission.revoke',
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structBefore: [
                'role_id' => $role->getId(),
                'role_slug' => $role->getSlug(),
                'permission_id' => $permission->getId(),
                'permission_slug' => $permission->getSlug(),
            ],
        );
    }
}
