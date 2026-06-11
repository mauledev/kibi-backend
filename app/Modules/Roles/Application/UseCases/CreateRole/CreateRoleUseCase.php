<?php

namespace App\Modules\Roles\Application\UseCases\CreateRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\CustomRoleLimitExceededException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use Illuminate\Support\Str;

class CreateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly SchoolRepositoryInterface $schools,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Create a new custom role for the tenant.
     * Only owner and school_manager actors may create custom roles.
     * The tenant must have custom_roles_limit configured and have capacity remaining.
     *
     * @throws HierarchyViolationException
     * @throws CustomRoleLimitExceededException
     */
    public function execute(CreateRoleInput $input): Role
    {
        if (! in_array($input->actorSlug, ['owner', 'school_manager'], true)) {
            throw new HierarchyViolationException(
                'Only owner or school_manager can create custom roles.'
            );
        }

        $limit = $this->roles->getCustomRolesLimit($input->tenantId);

        if ($limit === null || $this->roles->countCustomRoles($input->tenantId) >= $limit) {
            throw new CustomRoleLimitExceededException;
        }

        $slug = $input->slug ?? Str::slug($input->name, '_');

        $role = $this->roles->create(
            tenantId: $input->tenantId,
            categoryId: null,
            name: $input->name,
            slug: $slug,
            hierarchyLevel: 99,
            isSystemRole: false,
        );

        $schoolIds = array_filter(
            array_map(
                fn (string $uuid) => $this->schools->findIdByUuid($uuid),
                $input->schoolUuids
            )
        );

        if ($schoolIds !== []) {
            $this->roles->attachSchools($role->getId(), array_values($schoolIds));
        }

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
            'uuid' => $role->getUuid(),
            'tenant_id' => $role->getTenantId(),
            'category_id' => $role->getCategoryId(),
            'name' => $role->getName(),
            'slug' => $role->getSlug(),
            'hierarchy_level' => $role->getHierarchyLevel(),
            'is_system_role' => $role->isSystemRole(),
        ];
    }
}
