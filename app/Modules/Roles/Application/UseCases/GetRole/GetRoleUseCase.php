<?php

namespace App\Modules\Roles\Application\UseCases\GetRole;

use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

class GetRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Return a role by its public UUID, including its permissions.
     *
     * @throws RoleNotFoundException
     */
    public function execute(GetRoleInput $input): Role
    {
        $role = $this->roles->findByPublicId($input->publicId);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        return $role;
    }
}
