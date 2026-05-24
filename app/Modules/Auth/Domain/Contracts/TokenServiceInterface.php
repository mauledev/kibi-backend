<?php

namespace App\Modules\Auth\Domain\Contracts;

interface TokenServiceInterface
{
    /** @return string Plain text token */
    public function generate(int $userId): string;

    public function revokeById(int $tokenId): void;
}
