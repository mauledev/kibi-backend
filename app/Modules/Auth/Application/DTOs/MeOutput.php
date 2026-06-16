<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Roles\Domain\Entities\Role;

class MeOutput
{
    /**
     * @param  array<Role>  $roles
     * @param  array<string>  $permissions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly string $fullName,
        public readonly bool $isStaff,
        public readonly array $roles = [],
        public readonly array $permissions = [],
        // True when the user must accept the Responsible Use Policy before the
        // app grants access. Derived from roles + acceptance record.
        public readonly bool $mustAcceptPolicy = false,
    ) {}
}
