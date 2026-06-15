<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

/**
 * Local-dev helper: prints the CURRENT TOTP code for a user (or a raw base32
 * secret), so 2FA flows can be tested without an authenticator app.
 *
 * Usage:
 *   php artisan 2fa:code leader@kibi.com     # by user email (decrypts their secret)
 *   php artisan 2fa:code JBSWY3DPEHPK3PXP    # by raw secret (e.g. from the enrol QR screen)
 *
 * Refuses to run in production: exposing TOTP codes from the server defeats
 * the second factor.
 */
class TwoFactorCode extends Command
{
    protected $signature = '2fa:code {target : User email or raw base32 TOTP secret}';

    protected $description = 'Print the current TOTP code for a user or secret (local/testing only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $target = (string) $this->argument('target');

        if (str_contains($target, '@')) {
            $user = User::where('email', $target)->first();

            if ($user === null) {
                $this->error("No user found for \"{$target}\".");

                return self::FAILURE;
            }

            // The `encrypted` cast decrypts on access.
            $secret = $user->two_factor_secret;

            if ($secret === null) {
                $this->error("\"{$target}\" has no 2FA secret (not enrolled yet).");

                return self::FAILURE;
            }

            if ($user->two_factor_confirmed_at === null) {
                $this->warn('Note: enrolment is PENDING (secret exists but not confirmed).');
            }
        } else {
            $secret = $target;
        }

        $code = (new Google2FA)->getCurrentOtp($secret);
        $secondsLeft = 30 - (time() % 30);

        $this->info($code);
        $this->line("valid for ~{$secondsLeft}s");

        return self::SUCCESS;
    }
}
