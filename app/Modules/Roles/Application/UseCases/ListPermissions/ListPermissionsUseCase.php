<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\ListPermissions;

use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;

class ListPermissionsUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
    ) {}

    /**
     * Return all system permissions.
     *
     * @return array<Permission>
     */
    public function execute(): array
    {
        return $this->permissions->findAll();
    }
}
