<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\CreateRole;

class CreateRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly ?int $tenantId,
        public readonly string $name,
        public readonly string $slug,
        public readonly int $hierarchyLevel,
    ) {}
}
