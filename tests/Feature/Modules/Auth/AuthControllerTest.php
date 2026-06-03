<?php

use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear the array cache between tests to avoid hitting rate limits (5 req / 15 min).
    Cache::flush();
});

/**
 * Create a user scoped to a tenant by assigning them a tenant-level role
 * owned by that tenant. Accepts optional attributes for the user model.
 */
function authCreateTenantUser(Tenant $tenant, array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['tenant_id' => $tenant->id], $attributes));
    $role = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create([
        'slug' => 'auth_test_role_'.uniqid(),
    ]);
    UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

    return $user;
}

describe('AuthController', function () {
    describe('POST /api/auth/login', function () {
        it('returns 401 when email does not exist for the tenant', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'nobody@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 401 when password is wrong', function () {
            $tenant = Tenant::factory()->create(['slug' => 'wrongpw']);
            authCreateTenantUser($tenant, [
                'email' => 'user@test.com',
                'password_hash' => Hash::make('correct'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'wrong',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 401 when user is inactive', function () {
            $tenant = Tenant::factory()->create(['slug' => 'inactive-tenant']);
            authCreateTenantUser($tenant, [
                'email' => 'inactive@test.com',
                'password_hash' => Hash::make('secret'),
                'status' => 'inactive',
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'inactive@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 422 when email is missing', function () {
            $tenant = Tenant::factory()->create(['slug' => 'val-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', ['password' => 'secret'])
                ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('returns 422 when password is missing', function () {
            $tenant = Tenant::factory()->create(['slug' => 'val-tenant2']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', ['email' => 'user@test.com'])
                ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('returns 404 when tenant slug does not exist', function () {
            $this->withHeader('X-Tenant-Slug', 'nonexistent-slug')
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('returns 200 with token and uuid on valid credentials', function () {
            $tenant = Tenant::factory()->create(['slug' => 'valid-tenant']);
            authCreateTenantUser($tenant, [
                'email' => 'valid@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $response = $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'valid@test.com',
                    'password' => 'secret',
                ]);

            $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data' => ['uuid', 'email', 'first_name', 'last_name_paternal', 'full_name', 'is_staff', 'token', 'roles', 'permissions'],
                ]);

            // uuid must be a UUID, not an integer
            $id = $response->json('data.uuid');
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
            expect($response->json('data.is_staff'))->toBeFalse();
        });

        it('writes an auth.login.success audit log with the tenant', function () {
            $tenant = Tenant::factory()->create(['slug' => 'audit-tenant']);
            $user = authCreateTenantUser($tenant, [
                'email' => 'audit@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'audit@test.com',
                    'password' => 'secret',
                ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'auth.login.success',
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);
        });

        it('writes an auth.login.failed audit log with the email but never the password', function () {
            $tenant = Tenant::factory()->create(['slug' => 'audit-fail']);
            authCreateTenantUser($tenant, [
                'email' => 'audit-fail@test.com',
                'password_hash' => Hash::make('correct'),
            ]);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'audit-fail@test.com',
                    'password' => 'super-secret-wrong',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'auth.login.failed',
                'tenant_id' => $tenant->id,
            ]);

            $row = DB::table('audit_logs')
                ->where('action', 'auth.login.failed')
                ->latest('id')
                ->first();

            expect($row->struct_after)->toContain('audit-fail@test.com');
            expect($row->struct_after)->not->toContain('super-secret-wrong');
        });

        it('cannot login with credentials from a different tenant', function () {
            $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
            $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

            authCreateTenantUser($tenantA, [
                'email' => 'user@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            // Attempting to log in under tenantB with tenantA user credentials
            $this->withHeader('X-Tenant-Slug', $tenantB->slug)
                ->postJson('/api/auth/login', [
                    'email' => 'user@test.com',
                    'password' => 'secret',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });
    });

    describe('POST /api/staff/auth/login', function () {
        it('returns 401 when staff user does not exist', function () {
            $this->postJson('/api/staff/auth/login', [
                'email' => 'nobody@kibi.com',
                'password' => 'secret',
            ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
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
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 401 when a non-staff user tries to use the staff endpoint', function () {
            User::factory()->create([
                'email' => 'tenant@test.com',
                'password_hash' => Hash::make('secret'),
            ]);

            $this->postJson('/api/staff/auth/login', [
                'email' => 'tenant@test.com',
                'password' => 'secret',
            ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
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

            $response->assertStatus(Response::HTTP_OK)
                ->assertJsonPath('data.is_staff', true)
                ->assertJsonStructure(['data' => ['uuid', 'email', 'token']]);
        });

        it('returns 422 when email is missing in staff login', function () {
            $this->postJson('/api/staff/auth/login', ['password' => 'secret'])
                ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    });

    describe('GET /api/auth/me', function () {
        it('returns 401 when unauthenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 200 with user data when authenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-valid']);
            $user = authCreateTenantUser($tenant);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me');

            $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'data' => ['id', 'email', 'first_name', 'last_name_paternal', 'full_name', 'is_staff', 'roles', 'permissions'],
                ]);

            // id must be a UUID
            $id = $response->json('data.id');
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
        });

        it('returns user roles in the me response', function () {
            $tenant = Tenant::factory()->create(['slug' => 'me-roles']);
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            $role = RoleModel::factory()->forTenant($tenant)->atLevel(4)->create(['slug' => 'director', 'name' => 'Director']);
            UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->getJson('/api/auth/me');

            $response->assertStatus(Response::HTTP_OK);
            $roles = $response->json('data.roles');
            $slugs = array_column($roles, 'slug');
            expect($slugs)->toContain('director');
        });
    });

    describe('GET /api/staff/auth/me', function () {
        it('returns 401 when unauthenticated', function () {
            $this->getJson('/api/staff/auth/me')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 200 with isStaff true for authenticated staff user', function () {
            $staff = User::factory()->staff()->create();

            $response = $this->actingAs($staff)
                ->getJson('/api/staff/auth/me');

            $response->assertStatus(Response::HTTP_OK)
                ->assertJsonPath('data.is_staff', true);
        });
    });

    describe('POST /api/auth/logout', function () {
        it('returns 401 when unauthenticated', function () {
            $tenant = Tenant::factory()->create(['slug' => 'logout-tenant']);

            $this->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/logout')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('revokes the token and returns 200 on logout', function () {
            $tenant = Tenant::factory()->create(['slug' => 'logout-valid']);
            $user = authCreateTenantUser($tenant);

            // Create a real Sanctum token so currentAccessToken()->id is available.
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->withHeader('X-Tenant-Slug', $tenant->slug)
                ->postJson('/api/auth/logout');

            $response->assertStatus(Response::HTTP_OK);
        });
    });

    describe('POST /api/staff/auth/logout', function () {
        it('revokes staff token and returns 200', function () {
            $staff = User::factory()->staff()->create();

            // Create a real Sanctum token so currentAccessToken()->id is available.
            $token = $staff->createToken('staff-token')->plainTextToken;

            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/staff/auth/logout');

            $response->assertStatus(Response::HTTP_OK);
        });
    });
});
