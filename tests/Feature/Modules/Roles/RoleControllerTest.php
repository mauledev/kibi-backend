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
        // The owner bypasses all permission checks via the Gate::before hook.
        $this->owner = User::find($this->tenant->owner_id);
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'rc_owner_fixture',
        ]);
        assignRole($this->owner, $ownerFixtureRole);
    });

    describe('GET /api/tenant/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles')
                ->assertStatus(401);
        });

        it('returns 403 when user lacks role.view permission', function () {
            $user = User::factory()->create();
            // No roles assigned — no permissions

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles')
                ->assertStatus(403);
        });

        it('returns 200 with roles when user has role.view permission', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director', 'name' => 'Director']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles')
                ->assertStatus(200)
                ->assertJsonStructure(['success', 'data']);
        });

        it('owner bypasses permission check and can list roles', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles')
                ->assertStatus(200);
        });

        it('does not return roles from another tenant', function () {
            RoleModel::factory()->forTenant($this->otherTenant)->atLevel(5)->create(['slug' => 'other_tenant_role', 'name' => 'Other']);
            RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'my_tenant_role', 'name' => 'Mine']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles');

            $response->assertStatus(Response::HTTP_OK);

            $data = $response->json('data');
            $slugs = array_column($data, 'slug');

            expect($slugs)->toContain('my_tenant_role');
            expect($slugs)->not->toContain('other_tenant_role');
        });
    });

    describe('POST /api/tenant/roles (custom role creation)', function () {
        beforeEach(function () {
            $this->school = School::factory()->forTenant($this->tenant)->create();
            // Set a limit so custom roles can be created
            Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => 5]);
        });

        it('returns 403 when user lacks custom_role.create permission', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'rc_some_role']);
            assignRole($user, $role);
            grantPermission($role, 'role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/roles', [
                    'name' => 'New Role',
                    'slug' => 'new_role',
                    'school_uuids' => [$this->school->uuid],
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('creates a custom role and returns 201 when actor is owner', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/roles', [
                    'name' => 'New Director',
                    'slug' => 'new_director_cr',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(Response::HTTP_CREATED)
                ->assertJsonStructure(['success', 'data' => ['uuid', 'name', 'slug', 'hierarchy_level']]);

            expect($response->json('data.slug'))->toBe('new_director_cr');
        });

        it('returns 403 when actor is not owner or school_manager', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director_actor']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'custom_role.create');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/roles', [
                    'name' => 'Director Copy',
                    'slug' => 'director_copy_cr',
                    'school_uuids' => [$this->school->uuid],
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('owner can create a custom role', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/roles', [
                    'name' => 'Owner Custom',
                    'slug' => 'owner_custom',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(Response::HTTP_CREATED);
        });

        it('creates audit_log entry on successful role creation', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/roles', [
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
                ->postJson('/api/tenant/roles', [
                    'name' => 'Public Id Role',
                    'slug' => 'uuid_role_cr',
                    'school_uuids' => [$this->school->uuid],
                ]);

            $response->assertStatus(Response::HTTP_CREATED);

            $id = $response->json('data.uuid');

            // Must be a UUID, not an integer
            expect($id)->toBeString();
            expect(preg_match('/^[0-9a-f\-]{36}$/i', $id))->toBe(1);
        });
    });

    describe('GET /api/tenant/roles/{uuid}', function () {
        it('returns 404 for non-existent role', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns role with permissions when it exists', function () {
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'show_me', 'name' => 'Show Me']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/roles/{$targetRole->uuid}")
                ->assertStatus(200)
                ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'hierarchy_level', 'permissions']]);
        });

        it('show returns granted: true for an assigned permission', function () {
            $category = PermissionCategory::factory()->tenant()->create();
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'show_granted_true',
                'category_id' => $category->id,
            ]);
            $permission = PermissionModel::factory()->withSlug('budget.approve')->create(['category_id' => $category->id]);
            $targetRole->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/roles/{$targetRole->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $found = collect($permissions)->first(fn ($p) => $p['slug'] === 'budget.approve');

            expect($found)->not->toBeNull();
            expect($found['granted'])->toBeTrue();
        });

        it('show returns granted: false for an unassigned permission in the same category', function () {
            $category = PermissionCategory::factory()->tenant()->create();
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'show_granted_false',
                'category_id' => $category->id,
            ]);
            $assigned = PermissionModel::factory()->withSlug('budget.view')->create(['category_id' => $category->id]);
            $unassigned = PermissionModel::factory()->withSlug('budget.delete')->create(['category_id' => $category->id]);
            $targetRole->permissions()->attach($assigned->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/roles/{$targetRole->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $unassignedItem = collect($permissions)->first(fn ($p) => $p['slug'] === 'budget.delete');

            expect($unassignedItem)->not->toBeNull();
            expect($unassignedItem['granted'])->toBeFalse();
        });
    });

    describe('GET /api/tenant/roles (index granted field)', function () {
        it('index does not include a granted field on permission objects', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'index_no_granted']);
            grantPermission($role, 'role.view');
            grantPermission($role, 'index.granted.check');
            assignRole($user, $role);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/roles');

            $response->assertStatus(200);

            foreach ($response->json('data') as $roleItem) {
                foreach ($roleItem['permissions'] as $perm) {
                    expect(array_key_exists('granted', $perm))->toBeFalse();
                }
            }
        });
    });

    describe('PUT /api/tenant/roles/{uuid}', function () {
        it('updates role name and writes audit log when actor is school_manager', function () {
            $user = User::factory()->create();
            // Use the reserved 'school_manager' slug so the controller resolves it as an authorised actor.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['name' => 'Old Name', 'slug' => 'update_target']);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/roles/{$targetRole->uuid}", ['name' => 'New Name']);

            $response->assertStatus(Response::HTTP_OK);
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
                ->putJson("/api/tenant/roles/{$targetRole->uuid}", ['name' => 'Blocked'])
                ->assertStatus(403);
        });
    });

    describe('DELETE /api/tenant/roles/{uuid}', function () {
        it('soft-deletes role and writes audit log when actor is school_manager', function () {
            $user = User::factory()->create();
            // Use the reserved 'school_manager' slug so the controller resolves it as an authorised actor.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            assignRole($user, $actorRole);
            grantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'to_delete_role']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/roles/{$targetRole->uuid}")
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
                ->deleteJson("/api/tenant/roles/{$systemRole->uuid}")
                ->assertStatus(403);
        });

        it('returns 403 when trying to delete a non-custom tenant role (owner, school_manager)', function () {
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(2)->create(['slug' => 'owner']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/roles/{$ownerRole->uuid}")
                ->assertStatus(403);
        });
    });
});
