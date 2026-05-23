<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\AssignRoleToUser;

class AssignRoleToUserInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly int $targetUserId,
        public readonly string $rolePublicId,
        public readonly ?int $schoolId,
    ) {}
}
