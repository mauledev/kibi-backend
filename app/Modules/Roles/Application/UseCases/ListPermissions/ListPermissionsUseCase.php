<?php

namespace App\Modules\Roles\Application\UseCases\ListPermissions;

use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class ListPermissionsUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Return permissions available to a role when a roleUuid is provided.
     *
     * When roleUuid is provided:
     * - If the role does not exist, throws RoleNotFoundException.
     * - If the role is custom (no category), returns all permissions.
     * - Otherwise returns permissions from the role's category plus the 'common' category
     *   of the same scope (e.g. school/director returns school/director + school/common).
     *
     * When no roleUuid is provided, returns all permissions.
     *
     * @return array<Permission>
     *
     * @throws RoleNotFoundException when roleUuid is given but the role does not exist.
     */
    public function execute(?string $roleUuid = null): array
    {
        if ($roleUuid === null) {
            return $this->permissions->findAll();
        }

        $role = $this->roles->findByUuid($roleUuid);

        if ($role === null) {
            throw new RoleNotFoundException;
        }

        // Custom role or role without a category restriction → all permissions.
        if ($role->getCategoryId() === null) {
            return $this->permissions->findAll();
        }

        return $this->permissions->findByCategoryIdOrCommon($role->getCategoryId());
    }
}
