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

describe('PermissionController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        // The tenant owner is the user whose id matches TenantContext::ownerId.
        $this->owner = User::find($this->tenant->owner_id);
        // No level-1 role needed for PermissionController — no hierarchy checks
        // are involved in GET /api/permissions.
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
                ->assertStatus(Response::HTTP_UNAUTHORIZED);
        });

        it('returns 403 when user lacks manage.permissions', function () {
            $user = User::factory()->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'no_perm_view']);
            pcAssignRole($user, $role);
            // No manage.permissions granted

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions')
                ->assertStatus(Response::HTTP_FORBIDDEN);
        });

        it('returns 200 with permissions when user has manage.permissions', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_perm_list']);
            pcAssignRole($user, $actorRole);
            pcGrantPermission($actorRole, 'manage.permissions');

            // Create a system permission to list
            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('grade.view')->create(['category_id' => $category->id]);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions');

            $response->assertStatus(Response::HTTP_OK)
                ->assertJsonStructure([
                    'success',
                    'data',
                ]);

            $data = $response->json('data');
            expect($data)->toBeArray();
        });

        it('owner bypasses permission check and can list permissions', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions')
                ->assertStatus(Response::HTTP_OK);
        });

        it('returns permission uuids not internal ids', function () {
            $user = User::factory()->create();
            $actorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_uuid_check']);
            pcAssignRole($user, $actorRole);
            pcGrantPermission($actorRole, 'manage.permissions');

            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('payment.view')->create(['category_id' => $category->id]);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/permissions');

            $response->assertStatus(Response::HTTP_OK);

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
