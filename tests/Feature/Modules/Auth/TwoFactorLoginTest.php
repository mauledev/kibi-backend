<?php

use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear the array cache between tests (rate limiter + challenge store live here).
    Cache::flush();
});

/**
 * Create an active staff user with a single staff (system) role.
 */
function makeStaffWithRole(string $slug, string $password = 'secret'): User
{
    $user = User::factory()->staff()->create([
        'email' => $slug.'@kibi.com',
        'password_hash' => Hash::make($password),
    ]);

    $role = RoleModel::factory()->system()->create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'requires_2fa' => in_array($slug, ['leader', 'support'], true),
    ]);

    UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

    return $user;
}

/**
 * Mark a staff user as already enrolled in 2FA with a known secret.
 *
 * @return string The plaintext TOTP secret.
 */
function enrollStaffTwoFactor(User $user, array $recoveryCodes = ['AAAA-BBBB']): string
{
    $secret = (new Google2FA)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $recoveryCodes),
    ])->save();

    return $secret;
}

describe('Staff 2FA login', function () {
    it('issues a session directly when the role does not require 2FA', function () {
        makeStaffWithRole('operator');

        $this->postJson('/api/staff/auth/login', [
            'email' => 'operator@kibi.com',
            'password' => 'secret',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_staff', true)
            ->assertJsonStructure(['data' => ['uuid', 'email', 'token']]);
    });

    it('returns a setup_required challenge for an enforced role not yet enrolled', function () {
        makeStaffWithRole('leader');

        $response = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.two_factor.status', 'setup_required')
            ->assertJsonStructure(['data' => ['two_factor' => ['status', 'challenge_token']]]);

        // No auth token is issued at this stage.
        expect($response->json('data.token'))->toBeNull();
    });

    it('completes first-login enrollment: setup -> confirm -> session + recovery codes', function () {
        makeStaffWithRole('leader');

        $challengeToken = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ])->json('data.two_factor.challenge_token');

        $setup = $this->postJson('/api/staff/auth/2fa/setup', [
            'challenge_token' => $challengeToken,
        ]);

        $setup->assertStatus(200)
            ->assertJsonStructure(['data' => ['secret', 'provisioning_uri']]);

        $secret = $setup->json('data.secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $confirm = $this->postJson('/api/staff/auth/2fa/confirm', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ]);

        $confirm->assertStatus(200)
            ->assertJsonPath('data.session.is_staff', true)
            ->assertJsonStructure(['data' => ['session' => ['token'], 'recovery_codes']]);

        expect($confirm->json('data.recovery_codes'))->toBeArray()->not->toBeEmpty();
        expect(User::where('email', 'leader@kibi.com')->first()->hasTwoFactorEnabled())->toBeTrue();
    });

    it('rejects an invalid code during first-login confirmation', function () {
        makeStaffWithRole('leader');

        $challengeToken = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ])->json('data.two_factor.challenge_token');

        $this->postJson('/api/staff/auth/2fa/setup', ['challenge_token' => $challengeToken]);

        $this->postJson('/api/staff/auth/2fa/confirm', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422);
    });

    it('returns a required challenge for an enrolled enforced role and verifies the code', function () {
        $user = makeStaffWithRole('leader');
        $secret = enrollStaffTwoFactor($user);

        $login = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ]);

        $login->assertStatus(200)->assertJsonPath('data.two_factor.status', 'required');
        $challengeToken = $login->json('data.two_factor.challenge_token');

        $this->postJson('/api/staff/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_staff', true)
            ->assertJsonStructure(['data' => ['token']]);
    });

    it('accepts a recovery code at the challenge step', function () {
        $user = makeStaffWithRole('leader');
        enrollStaffTwoFactor($user, ['ZZZZ-1234']);

        $challengeToken = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ])->json('data.two_factor.challenge_token');

        $this->postJson('/api/staff/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => 'ZZZZ-1234',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token']]);
    });

    it('rejects an invalid code at the challenge step', function () {
        $user = makeStaffWithRole('leader');
        enrollStaffTwoFactor($user);

        $challengeToken = $this->postJson('/api/staff/auth/login', [
            'email' => 'leader@kibi.com',
            'password' => 'secret',
        ])->json('data.two_factor.challenge_token');

        $this->postJson('/api/staff/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422);
    });

    it('rejects an unknown challenge token', function () {
        $this->postJson('/api/staff/auth/2fa/setup', [
            'challenge_token' => 'does-not-exist',
        ])->assertStatus(401);

        $this->postJson('/api/staff/auth/2fa/challenge', [
            'challenge_token' => 'does-not-exist',
            'code' => '000000',
        ])->assertStatus(401);
    });
});
