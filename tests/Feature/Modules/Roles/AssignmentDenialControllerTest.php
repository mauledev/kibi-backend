<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Models\UserRoleAssignmentDenial;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function denialAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

function denialGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('Assignment Denial endpoints', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);

        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'denial_owner_fixture',
        ]);
        denialAssignRole($this->owner, $ownerFixtureRole);

        $this->school = School::factory()->forTenant($this->tenant)->create();

        // Target user and their director assignment (to be denied a permission)
        $this->targetUser = User::factory()->create();
        $this->directorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
            'slug' => 'director_denial_test',
            'name' => 'Director',
        ]);
        $this->targetAssignment = denialAssignRole($this->targetUser, $this->directorRole, $this->school->id);

        // A real permission to deny
        $this->permission = denialGrantPermission($this->directorRole, 'grade.delete');
    });

    describe('POST /users/{uuid}/assignments/{assignment_uuid}/denials', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials", [
                    'permission_uuid' => $this->permission->uuid,
                ])
                ->assertStatus(401);
        });

        it('returns 403 when actor lacks manage.permissions', function () {
            $unprivilegedUser = User::factory()->create();
            $unprivilegedRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create([
                'slug' => 'denial_unprivileged_fixture',
            ]);
            // No manage.permissions granted on this role
            denialAssignRole($unprivilegedUser, $unprivilegedRole);

            $this->actingAs($unprivilegedUser)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials", [
                    'permission_uuid' => $this->permission->uuid,
                ])
                ->assertStatus(403);
        });

        it('returns 403 when trying to add a denial on a gestor assignment', function () {
            $gestorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create([
                'slug' => 'gestor_escuelas',
            ]);
            $gestorUser = User::factory()->create();
            $gestorAssignment = denialAssignRole($gestorUser, $gestorRole, $this->school->id);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$gestorUser->uuid}/assignments/{$gestorAssignment->uuid}/denials", [
                    'permission_uuid' => $this->permission->uuid,
                ])
                ->assertStatus(403);
        });

        it('returns 201 when a valid denial is created', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials", [
                    'permission_uuid' => $this->permission->uuid,
                ]);

            $response->assertStatus(201);

            $this->assertDatabaseHas('user_role_assignment_denials', [
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);
        });

        it('returns 200 (idempotent) when the denial already exists', function () {
            // Create the denial upfront
            UserRoleAssignmentDenial::create([
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials", [
                    'permission_uuid' => $this->permission->uuid,
                ]);

            $response->assertStatus(200);

            // Still only one denial row — no duplicate was inserted
            expect(
                UserRoleAssignmentDenial::where('role_user_assignment_id', $this->targetAssignment->id)
                    ->where('permission_id', $this->permission->id)
                    ->count()
            )->toBe(1);
        });
    });

    describe('DELETE /users/{uuid}/assignments/{assignment_uuid}/denials/{permission_uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            UserRoleAssignmentDenial::create([
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials/{$this->permission->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 when actor lacks manage.permissions', function () {
            UserRoleAssignmentDenial::create([
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);

            $unprivilegedUser = User::factory()->create();
            $unprivilegedRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create([
                'slug' => 'denial_delete_unprivileged_fixture',
            ]);
            // No manage.permissions granted on this role
            denialAssignRole($unprivilegedUser, $unprivilegedRole);

            $this->actingAs($unprivilegedUser)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials/{$this->permission->uuid}")
                ->assertStatus(403);
        });

        it('returns 200 when the denial is removed', function () {
            UserRoleAssignmentDenial::create([
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials/{$this->permission->uuid}")
                ->assertStatus(200);

            $this->assertDatabaseMissing('user_role_assignment_denials', [
                'role_user_assignment_id' => $this->targetAssignment->id,
                'permission_id' => $this->permission->id,
            ]);
        });

        it('returns 200 when the denial does not exist (idempotent)', function () {
            // No denial in the database — still returns 200 gracefully
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->deleteJson("/api/users/{$this->targetUser->uuid}/assignments/{$this->targetAssignment->uuid}/denials/{$this->permission->uuid}")
                ->assertStatus(200);
        });
    });
});
