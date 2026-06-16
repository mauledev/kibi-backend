<?php

namespace App\Modules\Auth\Infrastructure\Services;

use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP engine backed by pragmarx/google2fa (shipped with pragmarx/google2fa-laravel).
 *
 * Stateless — no persistence and no session use. The `-laravel` package's
 * session middleware/facade is intentionally NOT used: staff auth is a
 * token-based API, so only the pure TOTP engine is consumed here, behind this
 * service so the rest of the app never touches the library directly.
 */
class Google2faService implements TwoFactorServiceInterface
{
    /**
     * Acceptance window in 30s steps on each side of the current slice.
     * 2 ⇒ ±60s, absorbing realistic authenticator-app/device clock drift
     * (the library default of 1 only tolerates ±30s).
     */
    private const VERIFY_WINDOW = 2;

    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    /** {@inheritDoc} */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /** {@inheritDoc} */
    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string
    {
        return $this->engine->getQRCodeUrl($issuer, $accountLabel, $secret);
    }

    /** {@inheritDoc} */
    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->engine->verifyKey($secret, $code, self::VERIFY_WINDOW);
    }

    /** {@inheritDoc} */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }
}
