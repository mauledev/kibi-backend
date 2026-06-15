<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Limiter buckets live in the cache — start every test from a clean slate.
    Cache::flush();

    // Small deterministic limits. The "login" limiter reads config inside its
    // closure on every request (AppServiceProvider), so per-test overrides work
    // — and a typo in any of these config keys would make these tests fail.
    config([
        'auth.login_throttle.max_attempts' => 3,
        'auth.login_throttle.decay_minutes' => 1,
        'auth.login_throttle.ip_max_attempts' => 10,
    ]);
});

/**
 * Bare user is enough for throttle tests: failed attempts never load roles and
 * throttled requests are rejected by the middleware before the controller runs.
 * (Unique name — Pest test-file functions are global; authCreateTenantUser
 * already exists in AuthControllerTest.php.)
 */
function throttleCreateUser(Tenant $tenant, string $email): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => $email,
        'password_hash' => Hash::make('correct-password'),
    ]);
}

function throttleTenantAttempt(Tenant $tenant, string $email, string $password = 'wrong-password')
{
    return test()
        ->withHeader('X-Tenant-Slug', $tenant->slug)
        ->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
}

describe('login throttle', function () {
    it('returns 429 with Retry-After after exhausting the attempts for one credential', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-basic']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 3) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        throttleTenantAttempt($tenant, 'victim@test.com')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertHeader('Retry-After');
    });

    it('keeps blocking even with the correct password once throttled', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-correct']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 3) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        // The middleware counts requests, not failures: the block is absolute
        // until the window decays, even for the legitimate owner of the account.
        throttleTenantAttempt($tenant, 'victim@test.com', 'correct-password')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('returns 429 on staff login after exhausting the attempts', function () {
        User::factory()->staff()->create([
            'email' => 'staff-victim@kibi.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/staff/auth/login', [
                'email' => 'staff-victim@kibi.com',
                'password' => 'wrong-password',
            ])->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        $this->postJson('/api/staff/auth/login', [
            'email' => 'staff-victim@kibi.com',
            'password' => 'wrong-password',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('does not block a different email from the same IP', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-email-iso']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 4) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com');
        }

        // The per-credential bucket is keyed by tenant + email + ip — a sibling
        // user on the same network must not be collateral damage.
        throttleTenantAttempt($tenant, 'someone-else@test.com')
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('does not block the same email under a different tenant', function () {
        $tenantA = Tenant::factory()->create(['slug' => 'throttle-tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'throttle-tenant-b']);
        throttleCreateUser($tenantA, 'shared@test.com');

        foreach (range(1, 4) as $attempt) {
            throttleTenantAttempt($tenantA, 'shared@test.com');
        }

        throttleTenantAttempt($tenantB, 'shared@test.com')
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('keeps tenant and staff buckets independent for the same email', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-scope']);
        throttleCreateUser($tenant, 'dual@test.com');

        foreach (range(1, 4) as $attempt) {
            throttleTenantAttempt($tenant, 'dual@test.com');
        }

        $this->postJson('/api/staff/auth/login', [
            'email' => 'dual@test.com',
            'password' => 'wrong-password',
        ])->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('does not block the same credential coming from a different client IP', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-ip-iso']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 4) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com');
        }

        // The IP is part of the per-credential key on purpose (Fortify-style):
        // an attacker exhausting the bucket cannot lock the real user out, who
        // logs in from their own address.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/auth/login', [
                'email' => 'victim@test.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('normalizes the email for the bucket key (case and whitespace)', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-norm']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 3) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com');
        }

        // Rotating the email casing must not buy the attacker a fresh bucket.
        throttleTenantAttempt($tenant, 'VICTIM@TEST.COM')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('enforces the per-IP backstop across many different emails', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-backstop']);

        // Email spraying: every attempt uses a fresh credential bucket, but all
        // of them count against the per-IP backstop (10 in this test).
        foreach (range(1, 10) as $i) {
            throttleTenantAttempt($tenant, "spray-{$i}@test.com")
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        }

        throttleTenantAttempt($tenant, 'spray-11@test.com')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    });

    it('lifts the block after the decay window passes', function () {
        $tenant = Tenant::factory()->create(['slug' => 'throttle-decay']);
        throttleCreateUser($tenant, 'victim@test.com');

        foreach (range(1, 3) as $attempt) {
            throttleTenantAttempt($tenant, 'victim@test.com');
        }

        throttleTenantAttempt($tenant, 'victim@test.com')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        // decay_minutes = 1 — one second past the window the bucket expires.
        // This is the test that catches a typo'd decay key: (int) null = 0
        // would mean a TTL of 0 and a throttle that never accumulates.
        $this->travel(61)->seconds();

        throttleTenantAttempt($tenant, 'victim@test.com')
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});
