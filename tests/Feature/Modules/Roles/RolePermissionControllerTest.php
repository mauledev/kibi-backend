<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

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
 * Find or create a permission with the given slug in a system category.
 * Using firstOrCreate to avoid unique slug constraint violations when the
 * same slug is used across multiple helpers in a test.
 */
function rpCreatePermission(string $slug): PermissionModel
{
    $existing = PermissionModel::where('slug', $slug)->first();
    if ($existing !== null) {
        return $existing;
    }

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
        // The tenant owner is the user whose id matches TenantContext::ownerId.
        // The owner bypasses all permission checks via the Gate::before hook.
        $this->owner = User::find($this->tenant->owner_id);
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'rp_owner_fixture',
        ]);
        rpGrantPermission($ownerFixtureRole, 'manage.permissions');
        rpAssignRole($this->owner, $ownerFixtureRole);
    });

    describe('POST /api/roles/{uuid}/permissions', function () {
        it('returns 401 when unauthenticated', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'some_role']);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$role->uuid}/permissions", [
                    'permission_uuid' => 'any-uuid',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'no_perm_role']);
            rpAssignRole($user, $actorRole);
            // No manage.permissions granted

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_perm']);
            $permission = rpCreatePermission('grade.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('assigns permission to role and writes audit log when valid', function () {
            $user = User::factory()->create();
            // Use the reserved school_manager slug so the controller resolves it as an authorised actor.
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'role_to_grant']);
            $permission = rpCreatePermission('payment.approve');

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ]);

            $response->assertStatus(Response::HTTP_OK);

            $this->assertDatabaseHas('role_permissions', [
                'role_id' => $targetRole->id,
                'permission_id' => $permission->id,
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'permission.grant',
                'user_id' => $user->id,
            ]);
        });

        it('returns 403 when actor is not an authorised permission manager', function () {
            // An actor with an unrecognised slug (not owner/school_manager/director)
            // triggers HierarchyViolationException → 403.
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'unknown_coord_actor']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'some_target_perm_role']);
            $permission = rpCreatePermission('role.view');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 403 when target role is a system role', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $systemRole = RoleModel::factory()->system()->atLevel(5)->create(['slug' => 'sys_perm_target']);
            $permission = rpCreatePermission('grade.publish');

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$systemRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 404 when permission does not exist', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_404']);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('owner bypasses gate and can assign permissions', function () {
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'owner_grant_target']);
            $permission = rpCreatePermission('schedule.view');

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(Response::HTTP_OK);
        });

        it('is idempotent — assigning already-present permission does not create duplicate audit log', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'idem_target']);
            $permission = rpCreatePermission('role.view');

            // Attach the permission already
            $targetRole->permissions()->attach($permission->id);

            // Make the request again
            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/roles/{$targetRole->uuid}/permissions", [
                    'permission_uuid' => $permission->uuid,
                ])
                ->assertStatus(Response::HTTP_OK);

            // No audit log should be written for idempotent call
            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'permission.grant',
            ]);
        });
    });

    describe('DELETE /api/roles/{uuid}/permissions/{permission_uuid}', function () {
        it('revokes permission from role and writes audit log', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            rpAssignRole($user, $actorRole);
            rpGrantPermission($actorRole, 'manage.permissions');

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'revoke_perm_target']);
            $permission = rpCreatePermission('payment.reject');
            $targetRole->permissions()->attach($permission->id);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->uuid}/permissions/{$permission->uuid}");

            $response->assertStatus(Response::HTTP_OK);

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
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_perm_revoke']);
            rpAssignRole($user, $actorRole);
            // No manage.permissions

            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'revoke_403_target']);
            $permission = rpCreatePermission('role.assign');
            $targetRole->permissions()->attach($permission->id);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/roles/{$targetRole->uuid}/permissions/{$permission->uuid}")
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });
    });
});
