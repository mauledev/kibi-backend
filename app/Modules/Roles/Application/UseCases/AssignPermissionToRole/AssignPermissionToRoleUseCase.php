<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\AssignPermissionToRole;

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Auth\Access\AuthorizationException;

class AssignPermissionToRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Assign a permission to a role.
     *
     * Rules:
     * - System roles never receive role_permissions rows.
     * - The actor must hold manage.permissions.
     * - The target role must have a strictly greater hierarchy_level than the actor.
     *
     * @throws AuthorizationException
     * @throws RoleNotFoundException
     * @throws PermissionNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(AssignPermissionToRoleInput $input): void
    {
        if (! $input->actorCanManagePermissions) {
            throw new AuthorizationException('Actor does not hold the manage.permissions permission.');
        }

        $role = $this->roles->findByPublicId($input->rolePublicId);

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

        $permission = $this->permissions->findByPublicId($input->permissionPublicId);

        if ($permission === null) {
            throw new PermissionNotFoundException;
        }

        // Idempotent — only insert if not already present
        if (! $role->hasPermission($permission->getSlug())) {
            $this->roles->attachPermission($role->getId(), $permission->getId());

            $this->audit->log(
                action: 'permission.grant',
                userId: $input->actorUserId,
                entityId: $role->getId(),
                structAfter: [
                    'role_id' => $role->getId(),
                    'role_slug' => $role->getSlug(),
                    'permission_id' => $permission->getId(),
                    'permission_slug' => $permission->getSlug(),
                ],
            );
        }
    }
}
