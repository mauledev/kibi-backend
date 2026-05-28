<?php

namespace App\Modules\Roles\Application\UseCases\AssignRoleToUser;

class AssignRoleToUserInput
{
    public function __construct(
        public readonly string $actorUuid,
        public readonly int $actorHierarchyLevel,
        public readonly string $targetUserUuid,
        public readonly string $roleUuid,
        public readonly ?string $schoolUuid,
    ) {}
}
