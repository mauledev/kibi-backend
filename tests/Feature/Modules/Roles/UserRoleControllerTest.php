<?php

declare(strict_types=1);

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    });

    describe('POST /api/users/{public_id}/roles', function () {
        it('returns 401 when unauthenticated', function () {
            $target = User::factory()->for($this->tenant)->create();

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => 'some-uuid',
                ])
                ->assertStatus(401);
        });

        it('returns 403 when actor lacks role.assign permission', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_assign_perm']);
            urAssignRole($actor, $actorRole);
            // No role.assign granted

            $target = User::factory()->for($this->tenant)->create();
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(7)->create(['slug' => 'docente_assign']);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => $roleToAssign->public_id,
                ])
                ->assertStatus(403);
        });

        it('assigns role to user and writes audit log when valid', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_assign']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->for($this->tenant)->create();
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_assign']);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => $roleToAssign->public_id,
                ]);

            $response->assertStatus(201);

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

        it('returns 403 when actor tries to assign role at same hierarchy level', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director_same']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->for($this->tenant)->create();
            $sameLevel = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'director_same_target']);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => $sameLevel->public_id,
                ])
                ->assertStatus(403);
        });

        it('returns 404 when target user does not exist', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            urAssignRole($actor, $actorRole);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/users/00000000-0000-0000-0000-000000000000/roles', [
                    'role_public_id' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(404);
        });

        it('returns 404 when role does not exist', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_404']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->for($this->tenant)->create();

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => '00000000-0000-0000-0000-000000000000',
                ])
                ->assertStatus(404);
        });

        it('returns existing assignment when same role already assigned (idempotent)', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_idem']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.assign');

            $target = User::factory()->for($this->tenant)->create();
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'idem_role']);

            // First assignment
            $existingAssignment = urAssignRole($target, $roleToAssign);

            // Second request — should be idempotent
            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => $roleToAssign->public_id,
                ]);

            $response->assertStatus(201);

            // Still only one assignment in the DB
            expect(
                UserRoleAssignment::where('user_id', $target->id)
                    ->where('role_id', $roleToAssign->id)
                    ->whereNull('revoked_at')
                    ->count()
            )->toBe(1);
        });

        it('owner bypasses permission check and can assign roles', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            urAssignRole($actor, $ownerRole);

            $target = User::factory()->for($this->tenant)->create();
            $roleToAssign = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create(['slug' => 'owner_assign_target']);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => $roleToAssign->public_id,
                ]);

            $response->assertStatus(201);
        });

        it('validation fails when role_public_id is not a uuid', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            urAssignRole($actor, $actorRole);

            $target = User::factory()->for($this->tenant)->create();

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$target->public_id}/roles", [
                    'role_public_id' => 'not-a-uuid',
                ])
                ->assertStatus(422);
        });
    });

    describe('DELETE /api/users/{public_id}/roles/{role_public_id}', function () {
        it('revokes role assignment and sets revoked_at', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_revoke']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->for($this->tenant)->create();
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke']);
            urAssignRole($target, $roleToRevoke);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$roleToRevoke->public_id}");

            $response->assertStatus(200);

            // Verify revoked_at is set, not hard-deleted
            $assignment = UserRoleAssignment::where('user_id', $target->id)
                ->where('role_id', $roleToRevoke->id)
                ->first();

            expect($assignment)->not->toBeNull();
            expect($assignment->revoked_at)->not->toBeNull();
        });

        it('creates audit log with action role.revoke', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_revoke_log']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->for($this->tenant)->create();
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke_log']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$roleToRevoke->public_id}")
                ->assertStatus(200);

            $this->assertDatabaseHas('audit_logs', [
                'action' => 'role.revoke',
                'user_id' => $actor->id,
            ]);
        });

        it('returns 404 when no active assignment exists (already revoked)', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_already_revoked']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->for($this->tenant)->create();
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'already_revoked_role']);

            // No active assignment — already revoked or never assigned
            UserRoleAssignment::factory()
                ->forUser($target)
                ->forRole($roleToRevoke)
                ->revoked()
                ->create();

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$roleToRevoke->public_id}")
                ->assertStatus(404);
        });

        it('returns 403 when actor tries to revoke role at same hierarchy level', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_revoke_same']);
            urAssignRole($actor, $actorRole);
            urGrantPermission($actorRole, 'role.revoke');

            $target = User::factory()->for($this->tenant)->create();
            $sameLevel = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'coord_same_level_target']);
            urAssignRole($target, $sameLevel);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$sameLevel->public_id}")
                ->assertStatus(403);
        });

        it('returns 403 when actor lacks role.revoke permission', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_no_revoke']);
            urAssignRole($actor, $actorRole);
            // No role.revoke granted

            $target = User::factory()->for($this->tenant)->create();
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'forbidden_revoke_target']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$roleToRevoke->public_id}")
                ->assertStatus(403);
        });

        it('owner can revoke any role assignment', function () {
            $actor = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            urAssignRole($actor, $ownerRole);

            $target = User::factory()->for($this->tenant)->create();
            $roleToRevoke = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'owner_revoke_target']);
            urAssignRole($target, $roleToRevoke);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$target->public_id}/roles/{$roleToRevoke->public_id}")
                ->assertStatus(200);
        });
    });

    describe('permission union and hasRole behavior', function () {
        it('user with two roles sharing a permission retains it after one role is revoked', function () {
            $tenant = $this->tenant;

            $user = User::factory()->for($tenant)->create();
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
            $user = User::factory()->for($tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($tenant)->owner()->create();
            urAssignRole($user, $ownerRole);

            $user->refresh();

            expect($user->hasRole('owner'))->toBeTrue();
        });

        it('hasRole returns false when the only owner assignment is revoked', function () {
            $tenant = $this->tenant;
            $user = User::factory()->for($tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($tenant)->owner()->create();
            $assignment = urAssignRole($user, $ownerRole);

            DB::table('user_role_assignments')
                ->where('id', $assignment->id)
                ->update(['revoked_at' => now()]);

            $user->refresh();

            expect($user->hasRole('owner'))->toBeFalse();
        });

        it('lowestHierarchyLevel returns PHP_INT_MAX when user has no active roles', function () {
            $user = User::factory()->for($this->tenant)->create();

            expect($user->lowestHierarchyLevel())->toBe(PHP_INT_MAX);
        });

        it('lowestHierarchyLevel returns the lowest level across active roles', function () {
            $tenant = $this->tenant;
            $user = User::factory()->for($tenant)->create();

            $level3 = RoleModel::factory()->forTenant($tenant)->atLevel(3)->create(['slug' => 'gestor_lvl3']);
            $level5 = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'coord_lvl5']);

            urAssignRole($user, $level3);
            urAssignRole($user, $level5);

            $user->refresh();

            expect($user->lowestHierarchyLevel())->toBe(3);
        });
    });

    describe('tenant isolation for user role assignments', function () {
        it('user from tenant A cannot see roles from tenant B', function () {
            $tenantA = $this->tenant;
            $tenantB = Tenant::factory()->create();

            $actorA = User::factory()->for($tenantA)->create();
            $ownerRoleA = RoleModel::factory()->forTenant($tenantA)->owner()->create();
            urAssignRole($actorA, $ownerRoleA);

            // Role that belongs to tenant B
            $roleBOnly = RoleModel::factory()->forTenant($tenantB)->atLevel(5)->create(['slug' => 'tenant_b_role']);

            $response = $this->actingAs($actorA)
                ->withHeader('X-Tenant-Slug', $tenantA->slug)
                ->getJson('/api/roles');

            $response->assertStatus(200);

            $slugs = array_column($response->json('data'), 'slug');
            expect($slugs)->not->toContain('tenant_b_role');
        });
    });
});
