<?php

use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear the array cache between tests to avoid hitting rate limits (5 req / 15 min).
    \Illuminate\Support\Facades\Cache::flush();
});

describe('AuthController', function () {
    describe('POST /api/auth/login', function () {
        it('returns 401 when email does not exist for the tenant', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'nobody@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(401);
        });

        it('returns 401 when password is wrong', function () {
            $tenant = Tenant::factory()->create(['slug' => 'wrongpw']);
            User::factory()->for($tenant)->create([
                'email' => 'user@test.com',
                'password_hash' => Hash::make('correct'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'wrong',
                ])
                ->assertStatus(401);
        });

        it('returns 401 when user is inactive', function () {
            $tenant = Tenant::factory()->create(['slug' => 'inactive-tenant']);
            User::factory()->for($tenant)->inactive()->create([
                'email' => 'inactive@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'inactive@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(401);
        });

        it('returns 422 when email is missing', function () {
            $tenant = Tenant::factory()->create(['slug' => 'val-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', ['password' => 'secret'])
                ->assertStatus(422);
        });

        it('returns 422 when password is missing', function () {
            $tenant = Tenant::factory()->create(['slug' => 'val-tenant2']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', ['email' => 'user@test.com'])
                ->assertStatus(422);
        });

        it('returns 404 when tenant slug does not exist', function () {
            $this->withHeader('X-Tenant-Slug', 'nonexistent-slug')
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(404);
        });

        it('returns 200 with token and uuid on valid credentials', function () {
            $tenant = Tenant::factory()->create(['slug' => 'valid-tenant']);
            User::factory()->for($tenant)->create([
                'email' => 'valid@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'valid@test.com',
                    'password' => 'secret',
                ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => ['uuid', 'email', 'full_name', 'is_staff', 'token', 'roles', 'permissions'],
                ]);

            // uuid must be a UUID, not an integer
            $id = $response->json('data.uuid');
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
            expect($response->json('data.is_staff'))->toBeFalse();
        });

        it('writes audit log on successful login', function () {
            $tenant = Tenant::factory()->create(['slug' => 'audit-tenant']);
            $user = User::factory()->for($tenant)->create([
                'email' => 'audit@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'audit@test.com',
                    'password' => 'secret',
                ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'auth.login',
                'user_id' => $user->id,
            ]);
        });

        it('cannot login with credentials from a different tenant', function () {
            $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
            $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

            User::factory()->for($tenantA)->create([
                'email' => 'user@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            // Attempting to log in under tenantB with tenantA user credentials
            $this->withHeader('X-Tenant-Slug', $tenantB->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(401);
        });
    });

    describe('POST /api/staff/auth/login', function () {
        it('returns 401 when staff user does not exist', function () {
            $this->postJson('/api/staff/auth/login', [
                'email' => 'nobody@kibi.com',
                'password' => 'secret',
            ])
                ->assertStatus(401);
        });

        it('returns 401 when password is wrong for staff user', function () {
            User::factory()->staff()->create([
                'email' => 'staff@kibi.com',
                'password_hash' => Hash::make('correct'),
            ]);

            $this->postJson('/api/staff/auth/login', [
                'email' => 'staff@kibi.com',
                'password' => 'wrong',
            ])
                ->assertStatus(401);
        });

        it('returns 401 when a tenant user tries to use the staff endpoint', function () {
            $tenant = Tenant::factory()->create();
            User::factory()->for($tenant)->create([
                'email' => 'tenant@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $this->postJson('/api/staff/auth/login', [
                'email' => 'tenant@test.com',
                'password' => 'secret',
            ])
                ->assertStatus(401);
        });

        it('returns 200 with isStaff true on valid staff credentials', function () {
            User::factory()->staff()->create([
                'email' => 'staff@kibi.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $response = $this->postJson('/api/staff/auth/login', [
                'email' => 'staff@kibi.com',
                'password' => 'secret',
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.is_staff', true)
                ->assertJsonStructure(['data' => ['uuid', 'email', 'token']]);
        });

        it('returns 422 when email is missing in staff login', function () {
            $this->postJson('/api/staff/auth/login', ['password' => 'secret'])
                ->assertStatus(422);
        });
    });

    describe('GET /api/auth/me', function () {
        it('returns 401 when unauthenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me')
                ->assertStatus(401);
        });

        it('returns 200 with user data when authenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-valid']);
            $user = User::factory()->for($tenant)->create();

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['id', 'email', 'full_name', 'is_staff', 'roles', 'permissions'],
                ]);

            // id must be a UUID
            $id = $response->json('data.id');
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
        });

        it('returns user roles in the me response', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-roles']);
            $user = User::factory()->for($tenant)->create();
            $role = RoleModel::factory()->forTenant($tenant)->atLevel(4)->create(['slug' => 'director', 'name' => 'Director']);
            UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me');

            $response->assertStatus(200);
            $roles = $response->json('data.roles');
            $slugs = array_column($roles, 'slug');
            expect($slugs)->toContain('director');
        });
    });

    describe('GET /api/staff/auth/me', function () {
        it('returns 401 when unauthenticated', function () {
            $this->getJson('/api/staff/auth/me')
                ->assertStatus(401);
        });

        it('returns 200 with isStaff true for authenticated staff user', function () {
            $staff = User::factory()->staff()->create();

            $response = $this->actingAs($staff)
                ->getJson('/api/staff/auth/me');

            $response->assertStatus(200)
                ->assertJsonPath('data.is_staff', true);
        });
    });

    describe('POST /api/auth/logout', function () {
        it('returns 401 when unauthenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'logout-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/logout')
                ->assertStatus(401);
        });

        it('revokes the token and returns 200 on logout', function () {
            $tenant = Tenant::factory()->create(['slug' => 'logout-valid']);
            $user = User::factory()->for($tenant)->create();

            // Create a real Sanctum token so currentAccessToken()->id is available.
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/logout');

            $response->assertStatus(200);
        });
    });

    describe('POST /api/staff/auth/logout', function () {
        it('revokes staff token and returns 200', function () {
            $staff = User::factory()->staff()->create();

            // Create a real Sanctum token so currentAccessToken()->id is available.
            $token = $staff->createToken('staff-token')->plainTextToken;

            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/staff/auth/logout');

            $response->assertStatus(200);
        });
    });
});
