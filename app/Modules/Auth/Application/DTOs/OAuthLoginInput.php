<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTOs;

class OAuthLoginInput
{
    public function __construct(
        public readonly string $provider,
        public readonly string $accessToken,
        public readonly int $tenantId,
    ) {}
}
