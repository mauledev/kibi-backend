<?php

namespace App\Modules\Auth\Application\DTOs;

class LoginInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $tenantId = null,
        public readonly ?string $ip = null,
    ) {}
}
