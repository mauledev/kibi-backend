<?php

namespace App\Modules\Roles\Application\UseCases\CreateRole;

class CreateRoleInput
{
    /**
     * @param  array<string>  $schoolUuids  School UUIDs where the custom role will be available.
     */
    public function __construct(
        public readonly int $actorUserId,
        public readonly string $actorSlug,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly array $schoolUuids,
        public readonly ?string $slug = null,
    ) {}
}
