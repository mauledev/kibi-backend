<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Find or create a permission with the given slug in a school-scoped category.
 */
function schoolCreatePermission(string $slug): PermissionModel
{
    $existing = PermissionModel::where('slug', $slug)->first();
    if ($existing !== null) {
        return $existing;
    }

    $category = PermissionCategory::factory()->school()->create();

    return PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
}

/**
 * Create a tenant-scoped custom role and link it to the given school.
 * Custom roles are the safe target for school permission assignment because
 * EloquentRoleRepository::attachPermission scopes to tenant_id.
 */
function createSchoolLinkedRole(Tenant $tenant, SchoolModel $school, string $slug): RoleModel
{
    $role = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create([
        'slug' => $slug,
        'name' => ucfirst($slug),
    ]);

    DB::table('custom_role_schools')->insert([
        'role_id' => $role->id,
        'school_id' => $school->id,
    ]);

    return $role;
}

describe('SchoolRoleController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        // The tenant owner bypasses all Gate permission checks via Gate::before.
        $this->owner = User::find($this->tenant->owner_id);

        // Give the owner a low-level fixture role so UseCase hierarchy checks pass.
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'src_owner_fixture',
        ]);
        UserRoleAssignment::factory()->forUser($this->owner)->forRole($ownerFixtureRole)->active()->create();

        $this->school = SchoolModel::factory()->forTenant($this->tenant)->create();

        // A second tenant to verify cross-tenant isolation.
        $this->otherTenant = Tenant::factory()->create();
        $this->otherSchool = SchoolModel::factory()->forTenant($this->otherTenant)->create();
    });

    describe('GET /api/tenant/schools/{uuid}/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles")
                ->assertStatus(401);
        });

        it('returns 403 when user has no role.view permission', function () {
            $user = User::factory()->create();
            // No roles assigned — no permissions.

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles")
                ->assertStatus(403);
        });

        it('owner bypasses permission check and receives 200', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles")
                ->assertStatus(200);
        });

        it('returns an array of roles for the school', function () {
            createSchoolLinkedRole($this->tenant, $this->school, 'src_director');

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles");

            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'data']);

            $slugs = array_column($response->json('data'), 'slug');
            expect($slugs)->toContain('src_director');
        });

        it('returns 404 when school UUID does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools/00000000-0000-0000-0000-000000000000/roles')
                ->assertStatus(404);
        });

        it('returns 404 when school belongs to another tenant', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->otherSchool->uuid}/roles")
                ->assertStatus(404);
        });
    });

    describe('GET /api/tenant/schools/{uuid}/roles/{role_uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_show_unauth');

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 when user has no role.view permission', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_show_403');
            $user = User::factory()->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}")
                ->assertStatus(403);
        });

        it('owner receives 200 with role data including permissions', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_show_200');
            $permission = schoolCreatePermission('grade.view.school');
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'hierarchy_level', 'permissions']]);

            expect($response->json('data.uuid'))->toBe($role->uuid);
        });

        it('response does not expose internal id', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_show_no_id');

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}");

            $response->assertStatus(200);
            expect(array_key_exists('id', $response->json('data')))->toBeFalse();
        });

        it('returns 404 when role UUID does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/00000000-0000-0000-0000-000000000000")
                ->assertStatus(404);
        });

        it('returns 404 when school UUID does not exist', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_show_404_school');

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/00000000-0000-0000-0000-000000000000/roles/{$role->uuid}")
                ->assertStatus(404);
        });

        it('show returns granted: true for an assigned permission', function () {
            // Global school operational role — mirrors production: tenant_id = NULL, school-scoped category.
            $category = PermissionCategory::factory()->school()->create();
            $role = RoleModel::factory()->global()->atLevel(5)->create([
                'slug' => 'src_show_granted_true',
                'category_id' => $category->id,
            ]);
            $permission = PermissionModel::factory()->withSlug('grade.granted.view')->create(['category_id' => $category->id]);
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $found = collect($permissions)->first(fn ($p) => $p['slug'] === 'grade.granted.view');

            expect($found)->not->toBeNull();
            expect($found['granted'])->toBeTrue();
        });

        it('show returns granted: false for an unassigned permission in the same category', function () {
            // Global school operational role — mirrors production: tenant_id = NULL, school-scoped category.
            $category = PermissionCategory::factory()->school()->create();
            $role = RoleModel::factory()->global()->atLevel(5)->create([
                'slug' => 'src_show_granted_false',
                'category_id' => $category->id,
            ]);
            $assigned = PermissionModel::factory()->withSlug('grade.granted.list')->create(['category_id' => $category->id]);
            $unassigned = PermissionModel::factory()->withSlug('grade.granted.delete')->create(['category_id' => $category->id]);
            $role->permissions()->attach($assigned->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $unassignedItem = collect($permissions)->first(fn ($p) => $p['slug'] === 'grade.granted.delete');

            expect($unassignedItem)->not->toBeNull();
            expect($unassignedItem['granted'])->toBeFalse();
        });
    });

    describe('GET /api/tenant/schools/{uuid}/roles (index granted field)', function () {
        it('index does not include a granted field on permission objects', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_index_no_granted');
            $permission = schoolCreatePermission('grade.index.granted.check');
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$this->school->uuid}/roles");

            $response->assertStatus(200);

            foreach ($response->json('data') as $roleItem) {
                foreach ($roleItem['permissions'] as $perm) {
                    expect(array_key_exists('granted', $perm))->toBeFalse();
                }
            }
        });
    });

    describe('POST /api/tenant/schools/{uuid}/roles/{role_uuid}/permissions', function () {
        it('returns 401 when unauthenticated', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_post_unauth');

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => 'any-uuid',
                ])
                ->assertStatus(401);
        });

        it('returns 403 for user without manage.permissions', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_post_403');
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'src_no_perm_role']);
            UserRoleAssignment::factory()->forUser($user)->forRole($actorRole)->active()->create();
            // No manage.permissions granted.

            $permission = schoolCreatePermission('grade.assign.school');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(403);
        });

        it('owner can assign a permission to a school role and receives 200', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_post_200');
            $permission = schoolCreatePermission('grade.submit.school');

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('role_permissions', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        });

        it('is idempotent — assigning already-present permission returns 200 without duplicate', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_idem');
            $permission = schoolCreatePermission('grade.idem.school');
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ]);

            $response->assertStatus(200);

            // No audit log should be written for an idempotent call.
            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'permission.grant',
            ]);
        });
    });

    describe('DELETE /api/tenant/schools/{uuid}/roles/{role_uuid}/permissions/{permission_uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_del_unauth');
            $permission = schoolCreatePermission('grade.del.v1');

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions/{$permission->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 for user without manage.permissions', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_del_403');
            $permission = schoolCreatePermission('grade.del.403');
            $role->permissions()->attach($permission->id);

            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'src_del_no_perm']);
            UserRoleAssignment::factory()->forUser($user)->forRole($actorRole)->active()->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions/{$permission->uuid}")
                ->assertStatus(403);
        });

        it('owner can revoke a permission from a school role and receives 200', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_del_200');
            $permission = schoolCreatePermission('grade.revoke.school');
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions/{$permission->uuid}");

            $response->assertStatus(200);

            $this->assertDatabaseMissing('role_permissions', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        });

        it('writes an audit log entry on successful permission revoke', function () {
            $role = createSchoolLinkedRole($this->tenant, $this->school, 'src_perm_del_audit');
            $permission = schoolCreatePermission('grade.revoke.audit');
            $role->permissions()->attach($permission->id);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/tenant/schools/{$this->school->uuid}/roles/{$role->uuid}/permissions/{$permission->uuid}");

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.revoke',
                'user_id' => $this->owner->id,
            ]);
        });
    });
});
