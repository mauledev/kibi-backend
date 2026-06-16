<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cache-backed 2FA login challenge store. The token is a random opaque string;
 * the cache entry maps it to a user id and expires after the configured TTL.
 */
class CacheTwoFactorChallengeRepository implements TwoFactorChallengeRepositoryInterface
{
    private const PREFIX = '2fa:challenge:';

    public function __construct(private readonly int $ttlSeconds) {}

    /** {@inheritDoc} */
    public function issue(int $userId): string
    {
        $token = Str::random(64);
        Cache::put(self::PREFIX.$token, $userId, $this->ttlSeconds);

        return $token;
    }

    /** {@inheritDoc} */
    public function resolve(string $token): ?int
    {
        $userId = Cache::get(self::PREFIX.$token);

        return $userId === null ? null : (int) $userId;
    }

    /** {@inheritDoc} */
    public function invalidate(string $token): void
    {
        Cache::forget(self::PREFIX.$token);
    }
}
