<?php

namespace App\Modules\Auth\Domain\Contracts;

/**
 * Persistence of a user's two-factor state. Generic over any user id.
 *
 * The secret and recovery codes are stored encrypted at rest (handled by the
 * User model casts). Recovery codes are stored hashed.
 */
interface TwoFactorRepositoryInterface
{
    /**
     * Store a freshly generated secret in a pending (not yet confirmed) state.
     */
    public function storePendingSecret(int $userId, string $secret): void;

    /**
     * Mark two-factor as confirmed and persist the hashed recovery codes.
     *
     * @param  array<string>  $hashedRecoveryCodes
     */
    public function confirm(int $userId, array $hashedRecoveryCodes): void;

    /**
     * Remove all two-factor data for the user (disable).
     */
    public function disable(int $userId): void;

    /**
     * Return the user's decrypted secret, or null when not enrolled.
     */
    public function getSecret(int $userId): ?string;

    /**
     * Whether the user has a confirmed two-factor enrollment.
     */
    public function isConfirmed(int $userId): bool;

    /**
     * Verify and burn a single-use recovery code. Returns true when matched.
     */
    public function consumeRecoveryCode(int $userId, string $code): bool;
}
