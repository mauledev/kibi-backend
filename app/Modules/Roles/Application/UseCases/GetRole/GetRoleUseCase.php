<?php

namespace App\Modules\Roles\Application\UseCases\GetRole;

use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class GetRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
    ) {}

    /**
     * Return a role by its public UUID, including its permissions and all available
     * permissions for the role's scope (used by the edit-permissions view).
     *
     * @throws RoleNotFoundException
     */
    public function execute(GetRoleInput $input): Role
    {
        $role = $this->roles->findByUuid($input->uuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        $available = $role->getCategoryId() !== null
            ? $this->permissions->findByCategoryId($role->getCategoryId())
            : $this->permissions->findAll();

        $role->setAvailablePermissions($available);

        return $role;
    }
}
