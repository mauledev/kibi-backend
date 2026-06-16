<?php

namespace App\Modules\Auth\Domain\Contracts;

/**
 * Abstraction over a TOTP (time-based one-time password) engine.
 *
 * Pure crypto operations — no persistence. Reusable by any audience
 * (staff, tenant users) through the two-factor use cases.
 */
interface TwoFactorServiceInterface
{
    /**
     * Generate a new base32 TOTP secret.
     */
    public function generateSecret(): string;

    /**
     * Build the `otpauth://` provisioning URI used to render the enrollment QR.
     */
    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string;

    /**
     * Verify a 6-digit TOTP code against the given secret.
     */
    public function verify(string $secret, string $code): bool;

    /**
     * Generate a fresh set of single-use recovery codes (plain text).
     *
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array;
}
