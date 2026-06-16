<?php

use App\Models\User;
use App\Modules\Auth\Application\UseCases\TwoFactor\ConfirmTwoFactorUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactor\DisableTwoFactorUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactor\EnrollTwoFactorUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactor\VerifyTwoFactorUseCase;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Auth\Domain\Exceptions\TwoFactorNotEnrolledException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function currentOtp(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

it('enrolls, confirms with a valid TOTP code, and verifies', function () {
    $user = User::factory()->create();

    $enrollment = app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');

    expect($enrollment->getSecret())->not->toBeEmpty();
    expect($enrollment->getProvisioningUri())->toStartWith('otpauth://');
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();

    $recoveryCodes = app(ConfirmTwoFactorUseCase::class)
        ->execute($user->id, currentOtp($enrollment->getSecret()));

    expect($recoveryCodes)->toHaveCount(8);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    // Verify with a fresh TOTP code.
    expect(app(VerifyTwoFactorUseCase::class)->execute($user->id, currentOtp($enrollment->getSecret())))
        ->toBeTrue();

    // Wrong code is rejected.
    expect(app(VerifyTwoFactorUseCase::class)->execute($user->id, '000000'))->toBeFalse();
});

it('reuses the pending secret on repeated enrollment (idempotent while unconfirmed)', function () {
    $user = User::factory()->create();
    $enroll = app(EnrollTwoFactorUseCase::class);

    $first = $enroll->execute($user->id, $user->email, 'Kibi');
    $second = $enroll->execute($user->id, $user->email, 'Kibi');

    expect($second->getSecret())->toBe($first->getSecret());

    // After confirming, a new enrollment must mint a fresh secret (no reuse of
    // the active one).
    app(ConfirmTwoFactorUseCase::class)->execute($user->id, currentOtp($first->getSecret()));
    $third = $enroll->execute($user->id, $user->email, 'Kibi');

    expect($third->getSecret())->not->toBe($first->getSecret());
});

it('stores the secret encrypted at rest', function () {
    $user = User::factory()->create();
    $enrollment = app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');

    $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

    // The raw column must not contain the plaintext secret.
    expect($raw)->not->toBeNull();
    expect($raw)->not->toBe($enrollment->getSecret());
    // But the model decrypts it back.
    expect($user->fresh()->two_factor_secret)->toBe($enrollment->getSecret());
});

it('accepts a recovery code once and then burns it', function () {
    $user = User::factory()->create();
    $enrollment = app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');
    $recoveryCodes = app(ConfirmTwoFactorUseCase::class)
        ->execute($user->id, currentOtp($enrollment->getSecret()));

    $verify = app(VerifyTwoFactorUseCase::class);

    expect($verify->execute($user->id, $recoveryCodes[0]))->toBeTrue();
    // Same code cannot be reused.
    expect($verify->execute($user->id, $recoveryCodes[0]))->toBeFalse();
});

it('disables two-factor', function () {
    $user = User::factory()->create();
    $enrollment = app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');
    app(ConfirmTwoFactorUseCase::class)->execute($user->id, currentOtp($enrollment->getSecret()));

    app(DisableTwoFactorUseCase::class)->execute($user->id);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('does not verify for a user who never confirmed', function () {
    $user = User::factory()->create();
    app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');

    // Enrolled but not confirmed → verify returns false even with a valid TOTP.
    $secret = $user->fresh()->two_factor_secret;
    expect(app(VerifyTwoFactorUseCase::class)->execute($user->id, currentOtp($secret)))->toBeFalse();
});

it('confirm throws when there is no pending secret', function () {
    $user = User::factory()->create();

    expect(fn () => app(ConfirmTwoFactorUseCase::class)->execute($user->id, '123456'))
        ->toThrow(TwoFactorNotEnrolledException::class);
});

it('confirm throws on an invalid code', function () {
    $user = User::factory()->create();
    app(EnrollTwoFactorUseCase::class)->execute($user->id, $user->email, 'Kibi');

    expect(fn () => app(ConfirmTwoFactorUseCase::class)->execute($user->id, '000000'))
        ->toThrow(InvalidTwoFactorCodeException::class);
});
