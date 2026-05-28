<?php

namespace App\Modules\Roles\Application\UseCases\DeleteRole;

class DeleteRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly string $uuid,
    ) {}
}
