<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function urAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

function urGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('UserRoleController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        // The tenant owner is automatically created by TenantFactory.
        // The owner bypasses all permission checks via the Gate::before hook.
        $this->owner = User::find($this->tenant->owner_id);
        $ownerRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'owner_role_fixture',
        ]);
        urAssignRole($this->owner, $ownerRole);
    });

    describe('POST /api/users/{uuid}/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'target_unauth']);
            urAssignRole($target, $targetRole);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => 'some-uuid',
                ])
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 403 when actor lacks role.assign permission', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_assign_perm']);
            urAssignRole($actor, $actorRole);
            // No role.assign granted

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'docente_assign']);
            urAssignRole($target, $roleToAssign);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $roleToAssign->uuid,
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('assigns role to user and writes audit log when valid', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetScopeRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_scope_assign']);
            urAssignRole($target, $targetScopeRole);

            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_assign']);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $roleToAssign->uuid,
                ]);

            $response->assertStatus(Response::HTTP_CREATED);

            $this->assertDatabaseHas('user_role_assignments', [
                'user_id' => $target->id,
                'role_id' => $roleToAssign->id,
                'revoked_at' => null,
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.assign',
                'user_id' => $actor->id,
            ]);
        });

        it('returns 403 when actor is not owner/gestor/director (slug-based check)', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'academic_coordinator']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'docente_x']);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $roleToAssign->uuid,
                ])
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 404 when target user does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/users/00000000-0000-0000-0000-000000000000/roles', [
                    'role_uuid' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('returns 404 when role does not exist', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetScopeRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_scope_404']);
            urAssignRole($target, $targetScopeRole);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('returns existing assignment when same role already assigned (idempotent)', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'idem_role']);

            // First assignment
            urAssignRole($target, $roleToAssign);

            // Second request — should be idempotent
            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $roleToAssign->uuid,
                ]);

            $response->assertStatus(Response::HTTP_CREATED);

            // Still only one assignment in the DB
            expect(
                UserRoleAssignment::where('user_id', $target->id)
                    ->where('role_id', $roleToAssign->id)
                    ->whereNull('revoked_at')
                    ->count()
            )->toBe(1);
        });

        it('owner bypasses permission check and can assign roles', function () {
            // The owner is the user whose id matches TenantContext::ownerId (set by TenantMiddleware).
            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetScopeRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'owner_target_scope']);
            urAssignRole($target, $targetScopeRole);

            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'owner_assign_target']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $roleToAssign->uuid,
                ]);

            $response->assertStatus(Response::HTTP_CREATED);
        });

        it('validation fails when role_uuid is not a uuid', function () {
            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetScopeRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_scope_val']);
            urAssignRole($target, $targetScopeRole);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => 'not-a-uuid',
                ])
                ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        it('returns 422 when trying to assign the owner role', function () {
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->create([
                'slug' => 'owner',
                'name' => 'Owner',
                'hierarchy_level' => 1,
            ]);

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $targetScopeRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'target_scope_owner']);
            urAssignRole($target, $targetScopeRole);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->uuid}/roles", [
                    'role_uuid' => $ownerRole->uuid,
                ]);

            $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    });

    describe('DELETE /api/users/{uuid}/roles/{role_uuid}', function () {
        it('revokes role assignment and sets revoked_at', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke']);
            urAssignRole($target, $roleToRevoke);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$roleToRevoke->uuid}");

            $response->assertStatus(Response::HTTP_OK);

            // Verify revoked_at is set, not hard-deleted
            $assignment = UserRoleAssignment::where('user_id', $target->id)
                ->where('role_id', $roleToRevoke->id)
                ->first();

            expect($assignment)->not->toBeNull();
            expect($assignment->revoked_at)->not->toBeNull();
        });

        it('creates audit log with action role.revoke', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke_log']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$roleToRevoke->uuid}")
                ->assertStatus(Response::HTTP_OK);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.revoke',
                'user_id' => $actor->id,
            ]);
        });

        it('returns 404 when no active assignment exists (already revoked)', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'already_revoked_role']);

            // No active assignment — already revoked or never assigned
            UserRoleAssignment::factory()
                ->forUser($target)
                ->forRole($roleToRevoke)
                ->revoked()
                ->create();

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$roleToRevoke->uuid}")
                ->assertStatus(Response::HTTP_NOT_FOUND);
        });

        it('returns 403 when actor tries to revoke role at same hierarchy level', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke_same']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $sameLevel = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_same_level_target']);
            urAssignRole($target, $sameLevel);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$sameLevel->uuid}")
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 403 when actor lacks role.revoke permission', function () {
            $actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_no_revoke']);
            urAssignRole($actor, $actorRole);
            // No role.revoke granted

            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'forbidden_revoke_target']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$roleToRevoke->uuid}")
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('owner can revoke any role assignment', function () {
            $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'owner_revoke_target']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->uuid}/roles/{$roleToRevoke->uuid}")
                ->assertStatus(Response::HTTP_OK);
        });
    });

    describe('permission union and hasRole behavior', function () {
        it('user with two roles sharing a permission retains it after one role is revoked', function () {
            $tenant = $this->tenant;

            $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $roleA = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'union_role_a']);
            $roleB = RoleModel::factory()->forTenant($tenant)->atLevel(6)->create(['slug' => 'union_role_b']);

            $category = PermissionCategory::factory()->system()->create();
            $sharedPerm = PermissionModel::factory()->withSlug('grade.view')->create(['category_id' => $category->id]);
            $roleA->permissions()->attach($sharedPerm->id);
            $roleB->permissions()->attach($sharedPerm->id);

            // Assign both roles
            $assignmentA = urAssignRole($user, $roleA);
            urAssignRole($user, $roleB);

            // Both active — user has the permission
            $user->refresh();
            expect($user->hasPermissionTo('grade.view'))->toBeTrue();

            // Revoke role A
            DB::table('user_role_assignments')
                ->where('id', $assignmentA->id)
                ->update(['revoked_at' => now()]);

            $user->refresh();

            // Role B still active and has the permission
            expect($user->hasPermissionTo('grade.view'))->toBeTrue();
        });

        it('hasRole returns true when any active assignment has that role slug', function () {
            $tenant = $this->tenant;
            $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $ownerRole = RoleModel::factory()->forTenant($tenant)->owner()->create();
            urAssignRole($user, $ownerRole);

            $user->refresh();

            expect($user->hasRole('owner'))->toBeTrue();
        });

        it('hasRole returns false when the only owner assignment is revoked', function () {
            $tenant = $this->tenant;
            $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $ownerRole = RoleModel::factory()->forTenant($tenant)->owner()->create();
            $assignment = urAssignRole($user, $ownerRole);

            DB::table('user_role_assignments')
                ->where('id', $assignment->id)
                ->update(['revoked_at' => now()]);

            $user->refresh();

            expect($user->hasRole('owner'))->toBeFalse();
        });

    });

    describe('tenant isolation for user role assignments', function () {
        it('user from tenant A cannot see roles from tenant B', function () {
            $tenantA = $this->tenant;
            $tenantB = Tenant::factory()->create();

            $ownerA = User::find($tenantA->owner_id);

            // Role that belongs to tenant B
            $roleBOnly = RoleModel::factory()->forTenant($tenantB)->atLevel(5)->create(['slug' => 'tenant_b_role']);

            $response = $this->actingAs($ownerA)
                ->withHeader('X-Tenant-Slug', $tenantA->slug)
                ->getJson('/api/roles');

            $response->assertStatus(Response::HTTP_OK);

            $slugs = array_column($response->json('data'), 'slug');
            expect($slugs)->not->toContain('tenant_b_role');
        });
    });
});
