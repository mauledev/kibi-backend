<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * Result of completing a 2FA login via setup+confirm: the issued session plus
 * the freshly generated recovery codes (shown to the user only this once).
 */
class TwoFactorLoginResult
{
    /**
     * @param  array<string>  $recoveryCodes
     */
    public function __construct(
        public readonly LoginOutput $session,
        public readonly array $recoveryCodes,
    ) {}
}
