<?php

declare(strict_types=1);

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: authenticate through TenantMiddleware via X-Tenant-Slug header.
 * Returns a configured test client acting as the given user.
 */
function tenantRequest(Tenant $tenant, User $user): \Illuminate\Testing\TestResponse
{
    return test()
        ->actingAs($user)
        ->withHeader('X-Tenant-Slug', $tenant->slug);
}

function assignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

function grantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('RoleController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
    });

    describe('GET /api/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(401);
        });

        it('returns 403 when user lacks role.view permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            // No roles assigned — no permissions

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(403);
        });

        it('returns 200 with roles when user has role.view permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director', 'name' => 'Director']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(200)
                ->assertJsonStructure(['success', 'data']);
        });

        it('owner bypasses permission check and can list roles', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $ownerRole);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(200);
        });

        it('does not return roles from another tenant', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $ownerRole);

            RoleModel::factory()->forTenant($this->otherTenant)->atLevel(5)->create(['slug' => 'other_tenant_role', 'name' => 'Other']);
            RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'my_tenant_role', 'name' => 'Mine']);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles');

            $response->assertStatus(200);

            $data = $response->json('data');
            $slugs = array_column($data, 'slug');

            expect($slugs)->toContain('my_tenant_role');
            expect($slugs)->not->toContain('other_tenant_role');
        });
    });

    describe('POST /api/roles', function () {
        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'some_role']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'New Role',
                    'slug' => 'new_role',
                    'hierarchy_level' => 7,
                ])
                ->assertStatus(403);
        });

        it('creates a role and returns 201 when actor has manage.permissions and valid hierarchy', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'New Director',
                    'slug' => 'new_director',
                    'hierarchy_level' => 4,
                ]);

            $response->assertStatus(201)
                ->assertJsonStructure(['success', 'data' => ['id', 'name', 'slug', 'hierarchy_level']]);

            expect($response->json('data.slug'))->toBe('new_director');
        });

        it('returns 403 when actor tries to create role at same hierarchy level', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director_actor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Director Copy',
                    'slug' => 'director_copy',
                    'hierarchy_level' => 4, // same level
                ])
                ->assertStatus(403);
        });

        it('owner can create a role at any hierarchy level', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $ownerRole);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Custom Level 3',
                    'slug' => 'custom_level_3',
                    'hierarchy_level' => 3,
                ]);

            $response->assertStatus(201);
        });

        it('creates audit_log entry on successful role creation', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_audit']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Audited Role',
                    'slug' => 'audited_role',
                    'hierarchy_level' => 5,
                ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.create',
                'user_id' => $user->id,
            ]);
        });

        it('response uses public_id not internal id', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_pubid']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Public Id Role',
                    'slug' => 'public_id_role',
                    'hierarchy_level' => 5,
                ]);

            $response->assertStatus(201);

            $id = $response->json('data.id');

            // Must be a UUID, not an integer
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
        });
    });

    describe('GET /api/roles/{public_id}', function () {
        it('returns 404 for non-existent role', function () {
            $user = User::factory()->for($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $role);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns role with permissions when it exists', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $ownerRole);

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'show_me', 'name' => 'Show Me']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/roles/{$targetRole->public_id}")
                ->assertStatus(200)
                ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'hierarchy_level', 'permissions']]);
        });
    });

    describe('PUT /api/roles/{public_id}', function () {
        it('updates role name and writes audit log', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_update']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['name' => 'Old Name', 'slug' => 'update_target']);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->public_id}", ['name' => 'New Name']);

            $response->assertStatus(200);
            expect($response->json('data.name'))->toBe('New Name');

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.update',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when actor tries to update role at same hierarchy level', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'same_level_actor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'same_level_target']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->public_id}", ['name' => 'Blocked'])
                ->assertStatus(403);
        });
    });

    describe('DELETE /api/roles/{public_id}', function () {
        it('soft-deletes role and writes audit log', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_delete']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'to_delete_role']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->public_id}")
                ->assertStatus(200);

            $this->assertSoftDeleted('roles', ['id' => $targetRole->id]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.delete',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when trying to delete a system role', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            assignRole($user, $ownerRole);

            $systemRole = RoleModel::factory()->system()->atLevel(5)->create(['slug' => 'sys_delete_attempt']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$systemRole->public_id}")
                ->assertStatus(403);
        });
    });
});
