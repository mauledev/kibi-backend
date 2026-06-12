<?php

namespace App\Modules\Roles\Application\UseCases\AssignPermissionToRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class AssignPermissionToRoleUseCase
{
    private const PROTECTED_SLUGS = ['superadmin', 'owner', 'school_manager'];

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Assign a permission to a role.
     *
     * Rules:
     * - The target role cannot be superadmin, owner, or school_manager.
     * - Actor slug must be owner (any role), school_manager (roles in their schools),
     *   or director (roles in their school, not gestor/owner roles).
     * - Permission category scope must match the role category scope (skipped for custom roles).
     *
     * @throws RoleNotFoundException
     * @throws PermissionNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(AssignPermissionToRoleInput $input): void
    {
        if (! in_array($input->actorSlug, ['owner', 'school_manager', 'director', 'superadmin'], true)) {
            throw new HierarchyViolationException(
                'Only owner, school_manager, director, or superadmin can manage role permissions.'
            );
        }

        $role = $this->roles->findByUuid($input->roleUuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if (in_array($role->getSlug(), self::PROTECTED_SLUGS, true)) {
            throw new SystemRoleViolationException;
        }

        // Superadmin can manage system roles (staff context); other actors cannot.
        if ($role->isSystemRole() && $input->actorSlug !== 'superadmin') {
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

        // Scope validation: permission category scope must match the role's category scope.
        // Custom roles (category_id = null) skip this check.
        if (! $role->isCustomRole() && $role->getCategoryId() !== null) {
            $roleScope = $this->permissions->findCategoryScope($role->getCategoryId());
            $permissionScope = $this->permissions->findCategoryScope($permission->getCategoryId());

            if ($roleScope !== null && $permissionScope !== null && $roleScope !== $permissionScope) {
                throw new HierarchyViolationException(
                    'The permission scope does not match the role category scope.'
                );
            }
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
