<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTOs;

class OAuthUserData
{
    public function __construct(
        public readonly string $providerId,
        public readonly string $email,
        public readonly string $name,
    ) {}
}
