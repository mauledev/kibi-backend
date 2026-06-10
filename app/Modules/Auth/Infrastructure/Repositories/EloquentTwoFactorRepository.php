<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Persists two-factor state on the users table.
 *
 * Uses Eloquent model instances + save() (never query-builder update) so the
 * `encrypted` casts on `two_factor_secret` / `two_factor_recovery_codes` apply.
 */
class EloquentTwoFactorRepository implements TwoFactorRepositoryInterface
{
    /** {@inheritDoc} */
    public function storePendingSecret(int $userId, string $secret): void
    {
        $user = UserModel::findOrFail($userId);
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();
    }

    /** {@inheritDoc} */
    public function confirm(int $userId, array $hashedRecoveryCodes): void
    {
        $user = UserModel::findOrFail($userId);
        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $hashedRecoveryCodes;
        $user->save();
    }

    /** {@inheritDoc} */
    public function disable(int $userId): void
    {
        $user = UserModel::findOrFail($userId);
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();
    }

    /** {@inheritDoc} */
    public function getSecret(int $userId): ?string
    {
        return UserModel::find($userId)?->two_factor_secret;
    }

    /** {@inheritDoc} */
    public function isConfirmed(int $userId): bool
    {
        return UserModel::find($userId)?->two_factor_confirmed_at !== null;
    }

    /** {@inheritDoc} */
    public function consumeRecoveryCode(int $userId, string $code): bool
    {
        $user = UserModel::find($userId);

        if ($user === null) {
            return false;
        }

        /** @var array<string> $codes */
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);
                $user->save();

                return true;
            }
        }

        return false;
    }
}
