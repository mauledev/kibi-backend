<?php

namespace App\Modules\Roles\Application\UseCases\UpdateRole;

class UpdateRoleInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly string $actorSlug,
        public readonly string $uuid,
        public readonly string $name,
    ) {}
}
