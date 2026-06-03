<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

/**
 * Helper: authenticate through TenantMiddleware via X-Tenant-Slug header.
 * Returns a configured test client acting as the given user.
 */
function tenantRequest(Tenant $tenant, User $user): TestResponse
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
        // The tenant owner is the user whose id matches TenantContext::ownerId.
        // Assign a low-level role so their lowestHierarchyLevel() allows creating/managing roles.
        $this->owner = User::find($this->tenant->owner_id);
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'rc_owner_fixture',
        ]);
        assignRole($this->owner, $ownerFixtureRole);
    });

    describe('GET /api/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 403 when user lacks role.view permission', function () {
            $user = User::factory()->create();
            // No roles assigned — no permissions

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 200 with roles when user has role.view permission', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director', 'name' => 'Director']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure(['success', 'data']);
        });

        it('owner bypasses permission check and can list roles', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(Response::HTTP_OK);
        });

        it('does not return roles from another tenant', function () {
            RoleModel::factory()->forTenant($this->otherTenant)->atLevel(5)->create(['slug' => 'other_tenant_role', 'name' => 'Other']);
            RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'my_tenant_role', 'name' => 'Mine']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles');

            $response->assertStatus(Response::HTTP_OK);

            $data = $response->json('data');
            $slugs = array_column($data, 'slug');

            expect($slugs)->toContain('my_tenant_role');
            expect($slugs)->not->toContain('other_tenant_role');
        });
    });

    describe('POST /api/roles', function () {
        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->create();
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
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('creates a role and returns 201 when actor has manage.permissions and valid hierarchy', function () {
            $user = User::factory()->create();
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

            $response->assertStatus(Response::HTTP_CREATED)
                ->assertJsonStructure(['success', 'data' => ['uuid', 'name', 'slug', 'hierarchy_level']]);

            expect($response->json('data.slug'))->toBe('new_director');
        });

        it('returns 403 when actor tries to create role at same hierarchy level', function () {
            $user = User::factory()->create();
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
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('owner can create a role at any hierarchy level', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Custom Level 3',
                    'slug' => 'custom_level_3',
                    'hierarchy_level' => 3,
                ]);

            $response->assertStatus(Response::HTTP_CREATED);
        });

        it('creates audit_log entry on successful role creation', function () {
            $user = User::factory()->create();
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

        it('response uses uuid not internal id', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_pubid']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Public Id Role',
                    'slug' => 'uuid_role',
                    'hierarchy_level' => 5,
                ]);

            $response->assertStatus(Response::HTTP_CREATED);

            $id = $response->json('data.uuid');

            // Must be a UUID, not an integer
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
        });
    });

    describe('GET /api/roles/{uuid}', function () {
        it('returns 404 for non-existent role', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles/00000000-0000-0000-0000-000000000000')
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('returns role with permissions when it exists', function () {
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'show_me', 'name' => 'Show Me']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/roles/{$targetRole->uuid}")
                ->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'hierarchy_level', 'permissions']]);
        });
    });

    describe('PUT /api/roles/{uuid}', function () {
        it('updates role name and writes audit log', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_update']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['name' => 'Old Name', 'slug' => 'update_target']);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->uuid}", ['name' => 'New Name']);

            $response->assertStatus(Response::HTTP_OK);
            expect($response->json('data.name'))->toBe('New Name');

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.update',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when actor tries to update role at same hierarchy level', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'same_level_actor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'same_level_target']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->uuid}", ['name' => 'Blocked'])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });
    });

    describe('DELETE /api/roles/{uuid}', function () {
        it('soft-deletes role and writes audit log', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_delete']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'to_delete_role']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->uuid}")
                ->assertStatus(Response::HTTP_OK);

            $this->assertSoftDeleted('roles', ['id' => $targetRole->id]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.delete',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when trying to delete a system role', function () {
            $systemRole = RoleModel::factory()->system()->atLevel(5)->create(['slug' => 'sys_delete_attempt']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$systemRole->uuid}")
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });
    });
});
