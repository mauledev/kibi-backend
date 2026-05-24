<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Roles\Domain\Entities\Role;

class LoginOutput
{
    /**
     * @param  array<Role>  $roles
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly bool $isStaff,
        public readonly string $token,
        public readonly array $roles = [],
    ) {}
}
