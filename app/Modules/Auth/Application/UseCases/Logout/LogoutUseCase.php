<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases\Logout;

use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;

class LogoutUseCase
{
    public function __construct(
        private readonly TokenServiceInterface $tokens,
    ) {}

    public function execute(int $tokenId): void
    {
        $this->tokens->revokeById($tokenId);
    }
}
