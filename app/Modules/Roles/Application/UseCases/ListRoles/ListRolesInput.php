<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\ListRoles;

class ListRolesInput
{
    public function __construct(
        /** Include only non-deleted roles when true */
        public readonly bool $excludeDeleted = true,
    ) {}
}
