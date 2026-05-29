<?php

namespace App\Modules\Auth\Application\DTOs;

class OAuthLoginInput
{
    public function __construct(
        public readonly string $provider,
        public readonly string $accessToken,
    ) {}
}
