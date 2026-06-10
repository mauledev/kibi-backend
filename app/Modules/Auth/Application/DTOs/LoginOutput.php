<?php

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Roles\Domain\Entities\Role;

class LoginOutput
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
        // Null when a session is intentionally withheld (e.g. activation of a
        // user whose role requires 2FA — they must sign in to complete 2FA).
        public readonly ?string $token,
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {}
}
