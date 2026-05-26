<?php

namespace App\Modules\Auth\Application\DTOs;

class RegisterInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $fullName,
    ) {}
}
