<?php

use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

/**
 * Build a pending staff user (no password / unverified) with a single staff
 * (system) role, ready for activation.
 */
function pendingStaffWithRole(string $slug): User
{
    $user = User::factory()->staff()->create([
        'email' => $slug.'.activate@kibi.com',
        'password_hash' => null,
        'email_verified_at' => null,
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
 * Build a pending owner + tenant fixture ready for activation.
 *
 * TenantFactory creates the owner and assigns the owner role.
 * We then reset the fields that the activation flow expects to find as null.
 *
 * @return array{tenant: Tenant, owner: User}
 */
function pendingOwnerFixture(): array
{
    $tenant = Tenant::factory()->create();
    $owner = User::find($tenant->owner_id);

    $owner->update([
        'email_verified_at' => null,
        'password_hash' => null,
    ]);

    $tenant->update(['status' => 'pending']);

    // Reload so the model state reflects the updates.
    $owner = $owner->fresh();
    $tenant = $tenant->fresh();

    return ['tenant' => $tenant, 'owner' => $owner];
}

/**
 * Generate a valid signed activation URL and parse its query params.
 *
 * @return array<string, string>
 */
function signedActivationParams(User $user): array
{
    $signedUrl = URL::temporarySignedRoute(
        'auth.activate',
        now()->addHours(48),
        ['user' => $user->uuid],
    );

    $parsed = parse_url($signedUrl);
    parse_str($parsed['query'] ?? '', $queryParams);

    return $queryParams;
}

describe('POST /api/auth/activate', function () {
    it('returns 422 when signature is invalid', function () {
        $this->postJson('/api/auth/activate?user=some-uuid&expires=9999999999&signature=invalid-sig', [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('returns 422 when signature is expired', function () {
        ['owner' => $owner] = pendingOwnerFixture();

        $expiredUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->subHour(),
            ['user' => $owner->uuid],
        );

        $parsed = parse_url($expiredUrl);
        parse_str($parsed['query'] ?? '', $queryParams);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('returns 404 when user uuid does not exist', function () {
        // Generate a validly signed URL but with a UUID that has no matching user.
        $nonExistentUuid = (string) Str::uuid();

        $signedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(48),
            ['user' => $nonExistentUuid],
        );

        $parsed = parse_url($signedUrl);
        parse_str($parsed['query'] ?? '', $queryParams);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])
            ->assertStatus(Response::HTTP_NOT_FOUND);
    });

    it('returns 422 when account is already activated', function () {
        // Create a tenant and leave email_verified_at set (factory default).
        $tenant = Tenant::factory()->create();
        $owner = User::find($tenant->owner_id);

        // Ensure email_verified_at is populated (account already active).
        $owner->update(['email_verified_at' => now()]);

        $queryParams = signedActivationParams($owner);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])
            ->assertStatus(Response::HTTP_NOT_FOUND);
    });

    it('activates account and returns token', function () {
        ['owner' => $owner] = pendingOwnerFixture();

        $queryParams = signedActivationParams($owner);

        $response = $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertStatus(Response::HTTP_OK);

        // Token must be present and non-empty.
        $token = $response->json('data.token');
        expect($token)->toBeString()->not->toBeEmpty();

        // password_hash must now be set.
        $this->assertDatabaseMissing('users', [
            'email' => $owner->email,
            'password_hash' => null,
        ]);

        // email_verified_at must now be set.
        $activatedUser = User::where('email', $owner->email)->firstOrFail();
        expect($activatedUser->email_verified_at)->not->toBeNull();
    });

    it('activates tenant to active status', function () {
        ['tenant' => $tenant, 'owner' => $owner] = pendingOwnerFixture();

        $queryParams = signedActivationParams($owner);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ])->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
        ]);
    });

    it('returns 422 when password_confirmation does not match', function () {
        ['owner' => $owner] = pendingOwnerFixture();

        $queryParams = signedActivationParams($owner);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'DifferentPass2!',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('withholds the session token when the staff role enforces 2FA', function () {
        $user = pendingStaffWithRole('support');

        $queryParams = signedActivationParams($user);

        $response = $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.is_staff', true);

        // Password was still set: the account is activated, only the session is withheld.
        $activated = User::where('email', $user->email)->firstOrFail();
        expect($activated->password_hash)->not->toBeNull();
        expect($activated->email_verified_at)->not->toBeNull();
    });

    it('issues a session token when the staff role does not enforce 2FA', function () {
        $user = pendingStaffWithRole('operator');

        $queryParams = signedActivationParams($user);

        $response = $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.is_staff', true);
        expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    });

    it('returns 422 when password is too short', function () {
        ['owner' => $owner] = pendingOwnerFixture();

        $queryParams = signedActivationParams($owner);

        $this->postJson('/api/auth/activate?'.http_build_query($queryParams), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    });
});
