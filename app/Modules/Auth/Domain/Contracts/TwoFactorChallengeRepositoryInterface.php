<?php

namespace App\Modules\Auth\Domain\Contracts;

/**
 * Short-lived store for the 2FA login challenge.
 *
 * When a login needs 2FA, we issue an opaque challenge token (not an auth
 * token) that maps to the user id for a few minutes. The 2FA endpoints resolve
 * it to complete the login. This keeps challenge tokens unable to access any
 * authenticated route (they are not Sanctum tokens).
 */
interface TwoFactorChallengeRepositoryInterface
{
    /**
     * Create a challenge for the user and return its opaque token.
     */
    public function issue(int $userId): string;

    /**
     * Resolve a challenge token to its user id, or null when invalid/expired.
     */
    public function resolve(string $token): ?int;

    /**
     * Invalidate a challenge token (after the login completes).
     */
    public function invalidate(string $token): void;
}
