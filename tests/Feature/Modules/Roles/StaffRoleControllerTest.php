<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a permission category with staff scope and a permission with the given slug.
 */
function staffCreatePermission(string $slug): PermissionModel
{
    $existing = PermissionModel::where('slug', $slug)->first();
    if ($existing !== null) {
        return $existing;
    }

    $category = PermissionCategory::factory()->staff()->create();

    return PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
}

/**
 * Grant a staff system role to a staff user, making them a superadmin.
 */
function makeSuperadmin(User $staff): RoleModel
{
    $superadmin = RoleModel::factory()->system()->create(['slug' => 'superadmin', 'name' => 'Superadmin']);
    UserRoleAssignment::factory()->forUser($staff)->forRole($superadmin)->active()->create();

    // Superadmin endpoints sit behind the Responsible Use Policy gate (SCRUM-520);
    // an operational superadmin has already accepted it.
    acceptPurFor($staff);

    return $superadmin;
}

describe('StaffRoleController', function () {
    beforeEach(function () {
        $this->staff = User::factory()->staff()->create();
        // A tenant user with no staff flag — used to verify 403 on staff-only routes.
        $this->tenant = Tenant::factory()->create();
        $this->tenantUser = User::factory()->create();
    });

    describe('GET /api/staff/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $this->getJson('/api/staff/roles')
                ->assertStatus(401);
        });

        it('returns 403 when called by a non-staff tenant user', function () {
            $this->actingAs($this->tenantUser)
                ->getJson('/api/staff/roles')
                ->assertStatus(403);
        });

        it('returns 200 with list of system roles when called by superadmin staff', function () {
            makeSuperadmin($this->staff);

            $role = RoleModel::factory()->system()->create(['slug' => 'support', 'name' => 'Support']);

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/roles');

            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'data']);

            $slugs = array_column($response->json('data'), 'slug');
            expect($slugs)->toContain('support');
        });

        it('returns roles with uuid, name, slug, and permissions fields', function () {
            makeSuperadmin($this->staff);

            RoleModel::factory()->system()->create(['slug' => 'helpdesk', 'name' => 'Helpdesk']);

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/roles');

            $response->assertStatus(200);

            $data = $response->json('data');
            expect($data)->not->toBeEmpty();

            $item = collect($data)->first(fn ($r) => $r['slug'] === 'helpdesk');
            expect($item)->toHaveKeys(['uuid', 'name', 'slug', 'permissions']);
        });

        it('does not expose internal id in the response', function () {
            makeSuperadmin($this->staff);

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/roles');

            $response->assertStatus(200);

            foreach ($response->json('data') as $item) {
                expect(array_key_exists('id', $item))->toBeFalse();
            }
        });

        it('returns 403 for staff user without a system role (no Gate bypass)', function () {
            // Staff user with no system role — Gate::before does not fire, Gate::after
            // checks manage.permissions which the user does not have.
            $this->actingAs($this->staff)
                ->getJson('/api/staff/roles')
                ->assertStatus(403);
        });
    });

    describe('GET /api/staff/roles/{uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_show']);

            $this->getJson("/api/staff/roles/{$role->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 when called by a non-staff tenant user', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_show_403']);

            $this->actingAs($this->tenantUser)
                ->getJson("/api/staff/roles/{$role->uuid}")
                ->assertStatus(403);
        });

        it('returns 200 with role data including permissions when called by superadmin', function () {
            makeSuperadmin($this->staff);

            $role = RoleModel::factory()->system()->create(['slug' => 'support_detail', 'name' => 'Support Detail']);
            $permission = staffCreatePermission('ticket.view');
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->staff)
                ->getJson("/api/staff/roles/{$role->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure(['data' => ['uuid', 'name', 'slug', 'hierarchy_level', 'permissions']]);

            expect($response->json('data.uuid'))->toBe($role->uuid);
            expect($response->json('data.slug'))->toBe('support_detail');
        });

        it('response does not expose internal id', function () {
            makeSuperadmin($this->staff);

            $role = RoleModel::factory()->system()->create(['slug' => 'support_no_id']);

            $response = $this->actingAs($this->staff)
                ->getJson("/api/staff/roles/{$role->uuid}");

            $response->assertStatus(200);
            expect(array_key_exists('id', $response->json('data')))->toBeFalse();
        });

        it('returns 404 when role UUID does not exist', function () {
            makeSuperadmin($this->staff);

            $this->actingAs($this->staff)
                ->getJson('/api/staff/roles/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('show returns granted: true for an assigned permission', function () {
            makeSuperadmin($this->staff);

            $category = PermissionCategory::factory()->staff()->create();
            $role = RoleModel::factory()->system()->create([
                'slug' => 'staff_show_granted_true',
                'name' => 'Staff Show Granted True',
                'category_id' => $category->id,
            ]);
            $permission = PermissionModel::factory()->withSlug('ticket.granted.view')->create(['category_id' => $category->id]);
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->staff)
                ->getJson("/api/staff/roles/{$role->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $found = collect($permissions)->first(fn ($p) => $p['slug'] === 'ticket.granted.view');

            expect($found)->not->toBeNull();
            expect($found['granted'])->toBeTrue();
        });

        it('show returns granted: false for an unassigned permission in the same category', function () {
            makeSuperadmin($this->staff);

            $category = PermissionCategory::factory()->staff()->create();
            $role = RoleModel::factory()->system()->create([
                'slug' => 'staff_show_granted_false',
                'name' => 'Staff Show Granted False',
                'category_id' => $category->id,
            ]);
            $assigned = PermissionModel::factory()->withSlug('ticket.granted.list')->create(['category_id' => $category->id]);
            $unassigned = PermissionModel::factory()->withSlug('ticket.granted.delete')->create(['category_id' => $category->id]);
            $role->permissions()->attach($assigned->id);

            $response = $this->actingAs($this->staff)
                ->getJson("/api/staff/roles/{$role->uuid}");

            $response->assertStatus(200);

            $permissions = $response->json('data.permissions');
            $unassignedItem = collect($permissions)->first(fn ($p) => $p['slug'] === 'ticket.granted.delete');

            expect($unassignedItem)->not->toBeNull();
            expect($unassignedItem['granted'])->toBeFalse();
        });
    });

    describe('GET /api/staff/roles (index granted field)', function () {
        it('index does not include a granted field on permission objects', function () {
            makeSuperadmin($this->staff);

            $category = PermissionCategory::factory()->staff()->create();
            $role = RoleModel::factory()->system()->create([
                'slug' => 'staff_index_no_granted',
                'name' => 'Staff Index No Granted',
                'category_id' => $category->id,
            ]);
            $permission = PermissionModel::factory()->withSlug('ticket.index.granted.check')->create(['category_id' => $category->id]);
            $role->permissions()->attach($permission->id);

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/roles');

            $response->assertStatus(200);

            foreach ($response->json('data') as $roleItem) {
                foreach ($roleItem['permissions'] as $perm) {
                    expect(array_key_exists('granted', $perm))->toBeFalse();
                }
            }
        });
    });

    describe('POST /api/staff/roles/{uuid}/permissions', function () {
        it('returns 401 when unauthenticated', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_perm_post']);

            $this->postJson("/api/staff/roles/{$role->uuid}/permissions", [
                'permission_uuid' => 'any-uuid',
            ])
                ->assertStatus(401);
        });

        it('returns 403 when called by a non-staff tenant user', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_perm_post_403']);
            $permission = staffCreatePermission('ticket.create.v1');

            $this->actingAs($this->tenantUser)
                ->postJson("/api/staff/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(403);
        });

        it('returns 403 for staff user without a system role (Gate check fails)', function () {
            // Regular staff without superadmin role — no Gate bypass, no manage.permissions grant.
            $role = RoleModel::factory()->system()->create(['slug' => 'support_perm_no_grant']);
            $permission = staffCreatePermission('ticket.escalate');

            $this->actingAs($this->staff)
                ->postJson("/api/staff/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(403);
        });

        it('superadmin staff user can assign a permission to a non-protected system role', function () {
            $superadminRole = makeSuperadmin($this->staff);

            // Create a target system role that is NOT a protected slug
            $targetRole = RoleModel::factory()->system()->create([
                'slug' => 'support_target',
                'name' => 'Support Target',
            ]);

            $permission = staffCreatePermission('ticket.resolve');

            $response = $this->actingAs($this->staff)
                ->postJson("/api/staff/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('role_permissions', [
                'role_id' => $targetRole->id,
                'permission_id' => $permission->id,
            ]);
        });

        it('writes an audit log entry on successful permission assignment', function () {
            makeSuperadmin($this->staff);

            $targetRole = RoleModel::factory()->system()->create([
                'slug' => 'support_audit',
                'name' => 'Support Audit',
            ]);

            $permission = staffCreatePermission('ticket.audit.assign');

            $this->actingAs($this->staff)
                ->postJson("/api/staff/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.grant',
                'user_id' => $this->staff->id,
            ]);
        });
    });

    describe('DELETE /api/staff/roles/{uuid}/permissions/{permission_uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_perm_del']);
            $permission = staffCreatePermission('ticket.delete.v1');

            $this->deleteJson("/api/staff/roles/{$role->uuid}/permissions/{$permission->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 when called by a non-staff tenant user', function () {
            $role = RoleModel::factory()->system()->create(['slug' => 'support_perm_del_403']);
            $permission = staffCreatePermission('ticket.close');

            $this->actingAs($this->tenantUser)
                ->deleteJson("/api/staff/roles/{$role->uuid}/permissions/{$permission->uuid}")
                ->assertStatus(403);
        });

        it('superadmin staff user can revoke a permission from a non-protected system role', function () {
            makeSuperadmin($this->staff);

            $targetRole = RoleModel::factory()->system()->create([
                'slug' => 'support_revoke_target',
                'name' => 'Support Revoke Target',
            ]);

            $permission = staffCreatePermission('ticket.revoke.test');
            $targetRole->permissions()->attach($permission->id);

            $response = $this->actingAs($this->staff)
                ->deleteJson("/api/staff/roles/{$targetRole->uuid}/permissions/{$permission->uuid}");

            $response->assertStatus(200);

            $this->assertDatabaseMissing('role_permissions', [
                'role_id' => $targetRole->id,
                'permission_id' => $permission->id,
            ]);
        });

        it('writes an audit log entry on successful permission revoke', function () {
            makeSuperadmin($this->staff);

            $targetRole = RoleModel::factory()->system()->create([
                'slug' => 'support_revoke_audit',
                'name' => 'Support Revoke Audit',
            ]);

            $permission = staffCreatePermission('ticket.revoke.audit');
            $targetRole->permissions()->attach($permission->id);

            $this->actingAs($this->staff)
                ->deleteJson("/api/staff/roles/{$targetRole->uuid}/permissions/{$permission->uuid}");

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.revoke',
                'user_id' => $this->staff->id,
            ]);
        });
    });
});
