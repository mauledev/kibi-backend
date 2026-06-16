<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * Returned by StaffLoginUseCase when credentials are valid but 2FA is pending.
 * No auth token is issued yet — the client must complete the 2FA step using the
 * challenge token.
 *
 * `status`:
 *  - 'setup_required' → the role requires 2FA and the user has not enrolled yet.
 *  - 'required'       → the user is enrolled and must enter a code.
 */
class TwoFactorChallenge
{
    public function __construct(
        public readonly string $status,
        public readonly string $challengeToken,
    ) {}
}
