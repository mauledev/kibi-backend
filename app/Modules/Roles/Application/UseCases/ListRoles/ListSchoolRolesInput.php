<?php

namespace App\Modules\Roles\Application\UseCases\ListRoles;

class ListSchoolRolesInput
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $tenantId,
    ) {}
}
