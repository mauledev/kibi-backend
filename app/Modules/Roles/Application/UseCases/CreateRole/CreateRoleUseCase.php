<?php

namespace App\Modules\Roles\Application\UseCases\CreateRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;

class CreateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Create a new tenant role.
     * The actor can only create roles with a hierarchy_level strictly greater than their own.
     *
     * @throws HierarchyViolationException
     */
    public function execute(CreateRoleInput $input): Role
    {
        if ($input->hierarchyLevel <= $input->actorHierarchyLevel) {
            throw new HierarchyViolationException(
                'You can only create roles with a hierarchy level strictly greater than your own.'
            );
        }

        $role = $this->roles->create(
            tenantId: $input->tenantId,
            name: $input->name,
            slug: $input->slug,
            hierarchyLevel: $input->hierarchyLevel,
            isSystemRole: false,
        );

        $this->audit->log(
            action: 'role.create',
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structAfter: $this->roleToArray($role),
        );

        return $role;
    }

    /** @return array<string, mixed> */
    private function roleToArray(Role $role): array
    {
        return [
            'id' => $role->getId(),
            'public_id' => $role->getPublicId(),
            'tenant_id' => $role->getTenantId(),
            'name' => $role->getName(),
            'slug' => $role->getSlug(),
            'hierarchy_level' => $role->getHierarchyLevel(),
            'is_system_role' => $role->isSystemRole(),
        ];
    }
}
