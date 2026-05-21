<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * RegisterInput DTO
 */
class RegisterInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $name,
        public readonly string $schoolId,
        public readonly string $role = 'user'
    ) {}
}
