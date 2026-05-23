<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Services;

use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumTokenService implements TokenServiceInterface
{
    /** {@inheritDoc} */
    public function generate(int $userId): string
    {
        return UserModel::findOrFail($userId)
            ->createToken('api', expiresAt: now()->addHours(24))
            ->plainTextToken;
    }

    /** {@inheritDoc} */
    public function revokeById(int $tokenId): void
    {
        PersonalAccessToken::findOrFail($tokenId)->delete();
    }
}
