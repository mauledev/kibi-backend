<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Roles\Domain\Entities\Role;

class MeOutput
{
    /**
     * @param  array<Role>    $roles
     * @param  array<string>  $permissions
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly bool $isStaff,
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {}
}
