<?php

namespace App\Modules\Auth\Application\DTOs;

class RegisterInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal = null,
    ) {}
}
