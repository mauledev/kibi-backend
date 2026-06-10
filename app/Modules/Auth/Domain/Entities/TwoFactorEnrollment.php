<?php

namespace App\Modules\Auth\Domain\Entities;

/**
 * Result of starting a two-factor enrollment: the secret (for manual entry)
 * and the `otpauth://` provisioning URI (for the QR). Returned once, at setup.
 */
class TwoFactorEnrollment
{
    public function __construct(
        private readonly string $secret,
        private readonly string $provisioningUri,
    ) {}

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getProvisioningUri(): string
    {
        return $this->provisioningUri;
    }
}
