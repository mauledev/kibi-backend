<?php

namespace App\Modules\Auth\Application\DTOs;

class LogoutInput
{
    public function __construct(
        public readonly int $tokenId,
        public readonly ?int $userId = null,
        public readonly ?int $tenantId = null,
        public readonly ?string $ip = null,
    ) {}
}
