<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

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
        // The owner bypasses all permission checks via the Gate::before hook.
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
                ->assertStatus(401);
        });

        it('returns 403 when user lacks role.view permission', function () {
            $user = User::factory()->create();
            // No roles assigned — no permissions

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(403);
        });

        it('returns 200 with roles when user has role.view permission', function () {
            $user = User::factory()->create();
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
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles')
                ->assertStatus(200);
        });

        it('does not return roles from another tenant', function () {
            RoleModel::factory()->forTenant($this->otherTenant)->atLevel(5)->create(['slug' => 'other_tenant_role', 'name' => 'Other']);
            RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'my_tenant_role', 'name' => 'Mine']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/roles');

            $response->assertStatus(200);

            $data = $response->json('data');
            $slugs = array_column($data, 'slug');

            expect($slugs)->toContain('my_tenant_role');
            expect($slugs)->not->toContain('other_tenant_role');
        });
    });

    describe('POST /api/roles (custom role creation)', function () {
        beforeEach(function () {
            $this->school = School::factory()->forTenant($this->tenant)->create();
            // Set a limit so custom roles can be created
            Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => 5]);
        });

        it('returns 403 when user lacks roles.custom.create permission', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'rc_some_role']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'New Role',
                    'slug' => 'new_role',
                    'school_uuids' => [$this->school->uuid],
                ])
                ->assertStatus(403);
        });

        it('creates a custom role and returns 201 when actor is owner', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'New Director',
                    'slug' => 'new_director_cr',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(201)
                ->assertJsonStructure(['success', 'data' => ['uuid', 'name', 'slug']]);

            expect($response->json('data.slug'))->toBe('new_director_cr');
        });

        it('returns 403 when actor is not owner or school_manager', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director_actor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'roles.custom.create');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Director Copy',
                    'slug' => 'director_copy_cr',
                    'school_uuids' => [$this->school->uuid],
                ])
                ->assertStatus(403);
        });

        it('owner can create a custom role', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Owner Custom',
                    'slug' => 'owner_custom',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(201);
        });

        it('creates audit_log entry on successful role creation', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Audited Role',
                    'slug' => 'audited_role_cr',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.create',
                'user_id' => $this->owner->id,
            ]);
        });

        it('response uses uuid not internal id', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/roles', [
                    'name' => 'Public Id Role',
                    'slug' => 'uuid_role_cr',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(201);

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
                ->assertStatus(404);
        });

        it('returns role with permissions when it exists', function () {
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'show_me', 'name' => 'Show Me']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/roles/{$targetRole->uuid}")
                ->assertStatus(200)
                ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'hierarchy_level', 'permissions']]);
        });
    });

    describe('PUT /api/roles/{uuid}', function () {
        it('updates role name and writes audit log when actor is school_manager', function () {
            $user = User::factory()->create();
            // Use the reserved 'school_manager' slug so the controller resolves it as an authorised actor.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['name' => 'Old Name', 'slug' => 'update_target']);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->uuid}", ['name' => 'New Name']);

            $response->assertStatus(200);
            expect($response->json('data.name'))->toBe('New Name');

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.update',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when actor is not an authorised role manager', function () {
            $user = User::factory()->create();
            // Unknown slug — not owner/school_manager/director → HierarchyViolationException → 403.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'unknown_actor_role']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'some_target_role']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/roles/{$targetRole->uuid}", ['name' => 'Blocked'])
                ->assertStatus(403);
        });
    });

    describe('DELETE /api/roles/{uuid}', function () {
        it('soft-deletes role and writes audit log when actor is school_manager', function () {
            $user = User::factory()->create();
            // Use the reserved 'school_manager' slug so the controller resolves it as an authorised actor.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'to_delete_role']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->uuid}")
                ->assertStatus(200);

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
                ->assertStatus(403);
        });
    });
});
