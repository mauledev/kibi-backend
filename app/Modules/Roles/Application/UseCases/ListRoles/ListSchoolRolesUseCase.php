<?php

namespace App\Modules\Roles\Application\UseCases\ListRoles;

use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

class ListSchoolRolesUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Return all roles available in the given school.
     * Includes system school-scoped roles and custom roles linked to this school.
     *
     * @return array<Role>
     */
    public function execute(ListSchoolRolesInput $input): array
    {
        return $this->roles->findBySchool($input->schoolId, $input->tenantId);
    }
}
