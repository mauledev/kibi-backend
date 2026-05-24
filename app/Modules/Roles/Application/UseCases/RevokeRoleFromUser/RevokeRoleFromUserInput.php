<?php

namespace App\Modules\Roles\Application\UseCases\RevokeRoleFromUser;

class RevokeRoleFromUserInput
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly int $actorHierarchyLevel,
        public readonly int $targetUserId,
        public readonly string $rolePublicId,
        public readonly ?int $schoolId,
    ) {}
}
