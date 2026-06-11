<?php

namespace App\Modules\Roles\Application\UseCases\RevokePermissionFromRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class RevokePermissionFromRoleUseCase
{
    private const PROTECTED_SLUGS = ['superadmin', 'owner', 'school_manager'];

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Revoke a permission from a role.
     * Actor slug must be owner, school_manager, or director.
     * Cannot revoke from system-protected roles.
     *
     * @throws RoleNotFoundException
     * @throws PermissionNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(RevokePermissionFromRoleInput $input): void
    {
        if (! in_array($input->actorSlug, ['owner', 'school_manager', 'director'], true)) {
            throw new HierarchyViolationException(
                'Only owner, school_manager, or director can manage role permissions.'
            );
        }

        $role = $this->roles->findByUuid($input->roleUuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if (in_array($role->getSlug(), self::PROTECTED_SLUGS, true)) {
            throw new SystemRoleViolationException;
        }

        // Director cannot manage gestor or owner roles
        if ($input->actorSlug === 'director' && in_array($role->getSlug(), ['owner', 'school_manager'], true)) {
            throw new HierarchyViolationException(
                'Director cannot manage permissions on owner or school_manager roles.'
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
