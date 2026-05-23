<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\UpdateRole;

class UpdateRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly string $publicId,
        public readonly string $name,
    ) {}
}
