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
 * Assign a role to a user without school context.
 */
function rpAssignRole(User $user, RoleModel $role): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create();
}

/**
 * Create a permission with the given slug in a system category.
 */
function rpCreatePermission(string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();

    return PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
}

/**
 * Grant a permission slug to a role.
 */
function rpGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $permission = rpCreatePermission($slug);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('RolePermissionController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
    });

    describe('POST /api/roles/{public_id}/permissions', function () {
        it('returns 401 when unauthenticated', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'some_role']);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$role->public_id}/permissions", [
                    'permission_public_id' => 'any-uuid',
                ])
                ->assertStatus(401);
        });

        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'no_perm_role']);
            rpAssignRole($user, $actorRole);
            // No manage.permissions granted

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_perm']);
            $permission = rpCreatePermission('grade.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ])
                ->assertStatus(403);
        });

        it('assigns permission to role and writes audit log when valid', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_perm']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'role_to_grant']);
            $permission = rpCreatePermission('payment.approve');

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('role_permissions', [
                'role_id' => $targetRole->id,
                'permission_id' => $permission->id,
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.grant',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when target role has same hierarchy as actor', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_actor']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $sameLevel = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_same']);
            $permission = rpCreatePermission('role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$sameLevel->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ])
                ->assertStatus(403);
        });

        it('returns 403 when target role is a system role', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_sys']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $systemRole = RoleModel::factory()->system()->atLevel(5)->create(['slug' => 'sys_perm_target']);
            $permission = rpCreatePermission('grade.publish');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$systemRole->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ])
                ->assertStatus(403);
        });

        it('returns 404 when permission does not exist', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_404']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_404']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->public_id}/permissions", [
                    'permission_public_id' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(404);
        });

        it('owner bypasses gate and can assign permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            rpAssignRole($user, $ownerRole);

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'owner_grant_target']);
            $permission = rpCreatePermission('schedule.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ])
                ->assertStatus(200);
        });

        it('is idempotent — assigning already-present permission does not create duplicate audit log', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_idem']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'idem_target']);
            $permission = rpCreatePermission('role.view');

            // Attach the permission already
            $targetRole->permissions()->attach($permission->id);

            // Make the request again
            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->public_id}/permissions", [
                    'permission_public_id' => $permission->public_id,
                ])
                ->assertStatus(200);

            // No audit log should be written for idempotent call
            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'permission.grant',
            ]);
        });
    });

    describe('DELETE /api/roles/{public_id}/permissions/{permission_public_id}', function () {
        it('revokes permission from role and writes audit log', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_revoke_perm']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'revoke_perm_target']);
            $permission = rpCreatePermission('payment.reject');
            $targetRole->permissions()->attach($permission->id);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->public_id}/permissions/{$permission->public_id}");

            $response->assertStatus(200);

            $this->assertDatabaseMissing('role_permissions', [
                'role_id' => $targetRole->id,
                'permission_id' => $permission->id,
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.revoke',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when actor lacks manage.permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_perm_revoke']);
            rpAssignRole($user, $actorRole);
            // No manage.permissions

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'revoke_403_target']);
            $permission = rpCreatePermission('role.assign');
            $targetRole->permissions()->attach($permission->id);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->public_id}/permissions/{$permission->public_id}")
                ->assertStatus(403);
        });
    });
});
