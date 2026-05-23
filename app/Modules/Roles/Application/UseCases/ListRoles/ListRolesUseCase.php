<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\ListRoles;

use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

class ListRolesUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Return all roles visible to the current tenant.
     *
     * @return array<Role>
     */
    public function execute(ListRolesInput $input): array
    {
        $roles = $this->roles->findAll();

        if ($input->excludeDeleted) {
            return array_values(
                array_filter($roles, fn (Role $r) => ! $r->isDeleted())
            );
        }

        return $roles;
    }
}
