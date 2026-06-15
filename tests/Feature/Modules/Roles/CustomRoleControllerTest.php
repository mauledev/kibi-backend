<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

function crGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('POST /api/tenant/roles/custom', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);

        // Give owner a low-level role so UseCase hierarchy checks pass
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'cr_owner_fixture',
        ]);
        crAssignRole($this->owner, $ownerFixtureRole);

        // Create a real school to pass in school_uuids
        $this->school = School::factory()->forTenant($this->tenant)->create();
    });

    it('returns 403 when actor is a teacher (not owner or gestor)', function () {
        $teacher = User::factory()->create();
        $teacherRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'docente_cr_test']);
        crAssignRole($teacher, $teacherRole, $this->school->id);

        $this->actingAs($teacher)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'Blocked Custom Role',
                'slug' => 'blocked_custom',
                'school_uuids' => [$this->school->uuid],
            ])
            ->assertStatus(403);
    });

    it('returns 422 when school_uuids is empty', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'Valid Custom Role',
                'slug' => 'valid_custom',
                'school_uuids' => [],
            ])
            ->assertStatus(422);
    });

    it('returns 422 when school_uuids is missing', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'Valid Custom Role',
                'slug' => 'valid_custom',
            ])
            ->assertStatus(422);
    });

    it('returns 201 with the created role when actor is owner and limit is not exceeded', function () {
        // Set a custom role limit on the tenant
        Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => 5]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'My Custom Role',
                'slug' => 'my_custom_role',
                'school_uuids' => [$this->school->uuid],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['uuid', 'name', 'slug']]);

        expect($response->json('data.slug'))->toBe('my_custom_role');

        // Verify custom_role_schools row was created
        $this->assertDatabaseHas('custom_role_schools', [
            'school_id' => $this->school->id,
        ]);
    });

    it('returns 409 when custom role limit is exceeded', function () {
        // Set limit to 1 and create an existing custom role to fill the slot
        Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => 1]);

        RoleModel::factory()->forTenant($this->tenant)->create([
            'slug' => 'existing_custom',
            'name' => 'Existing Custom',
            'category_id' => null,
        ]);

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'One More Custom',
                'slug' => 'one_more_custom',
                'school_uuids' => [$this->school->uuid],
            ])
            ->assertStatus(409);
    });

    it('returns 409 when custom_roles_limit is null (not configured)', function () {
        // tenant has custom_roles_limit = null by default (not configured)
        Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => null]);

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'Blocked By Null Limit',
                'slug' => 'blocked_null_limit',
                'school_uuids' => [$this->school->uuid],
            ])
            ->assertStatus(409);
    });

    it('response exposes uuid not internal id', function () {
        Tenant::where('id', $this->tenant->id)->update(['custom_roles_limit' => 5]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/roles/custom', [
                'name' => 'Uuid Check Role',
                'slug' => 'uuid_check_role',
                'school_uuids' => [$this->school->uuid],
            ]);

        $response->assertStatus(201);

        $uuid = $response->json('data.uuid');
        expect($uuid)->toBeString();
        expect(preg_match('/^[0-9a-f\-]{36}$/i', $uuid))->toBe(1);
    });
});
