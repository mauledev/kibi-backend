<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PermissionController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
    });

    function pcAssignRole(User $user, RoleModel $role): UserRoleAssignment
    {
        return UserRoleAssignment::factory()
            ->forUser($user)
            ->forRole($role)
            ->active()
            ->create();
    }

    function pcGrantPermission(RoleModel $role, string $slug): PermissionModel
    {
        $category = PermissionCategory::factory()->system()->create();
        $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
        $role->permissions()->attach($permission->id);

        return $permission;
    }

    describe('GET /api/permissions', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions')
                ->assertStatus(401);
        });

        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_perm_view']);
            pcAssignRole($user, $role);
            // No manage.permissions granted

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions')
                ->assertStatus(403);
        });

        it('returns 200 with permissions when user has manage.permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_perm_list']);
            pcAssignRole($user, $actorRole);
            pcGrantPermission($actorRole, 'manage.permissions');

            // Create a system permission to list
            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('grade.view')->create(['category_id' => $category->id]);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                ]);

            $data = $response->json('data');
            expect($data)->toBeArray();
        });

        it('owner bypasses permission check and can list permissions', function () {
            $user = User::factory()->for($this->tenant)->create();
            $ownerRole = RoleModel::factory()->forTenant($this->tenant)->owner()->create();
            pcAssignRole($user, $ownerRole);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions')
                ->assertStatus(200);
        });

        it('returns permission uuids not internal ids', function () {
            $user = User::factory()->for($this->tenant)->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_uuid_check']);
            pcAssignRole($user, $actorRole);
            pcGrantPermission($actorRole, 'manage.permissions');

            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('payment.view')->create(['category_id' => $category->id]);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions');

            $response->assertStatus(200);

            $data = $response->json('data');

            if (count($data) > 0) {
                $permId = $data[0]['id'] ?? null;
                if ($permId !== null) {
                    // Must be UUID format, not an integer
                    expect(is_string($permId))->toBeTrue();
                    expect(preg_match('/^[0-9a-f\-]{36}$/i', $permId))->toBe(1);
                }
            }
        });
    });
});
