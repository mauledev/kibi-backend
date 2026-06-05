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

function spAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

describe('GET /api/schools/{uuid}/permissions?role_uuid=X', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);

        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'sp_owner_fixture',
        ]);
        spAssignRole($this->owner, $ownerFixtureRole);

        $this->school = School::factory()->forTenant($this->tenant)->create();
    });

    it('returns only permissions from the role category when the role has a category', function () {
        // Create two categories
        $directorCategory = PermissionCategory::factory()->create(['name' => 'director_category', 'scope' => 'school']);
        $financeCategory = PermissionCategory::factory()->create(['name' => 'finance_category', 'scope' => 'school']);

        // Create permissions in each category
        $directorPerm = PermissionModel::factory()->withSlug('grade.view')->create(['category_id' => $directorCategory->id]);
        $financePerm = PermissionModel::factory()->withSlug('invoice.view')->create(['category_id' => $financeCategory->id]);

        // Director role is scoped to director category
        $directorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
            'slug' => 'director_sp_test',
            'category_id' => $directorCategory->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->getJson("/api/schools/{$this->school->uuid}/permissions?role_uuid={$directorRole->uuid}");

        $response->assertStatus(200);

        $returnedSlugs = array_column($response->json('data'), 'slug');

        expect($returnedSlugs)->toContain('grade.view');
        expect($returnedSlugs)->not->toContain('invoice.view');
    });

    it('returns all permissions when the role is custom (no category)', function () {
        // Create permissions from multiple categories
        $cat1 = PermissionCategory::factory()->create(['name' => 'cat_one', 'scope' => 'school']);
        $cat2 = PermissionCategory::factory()->create(['name' => 'cat_two', 'scope' => 'tenant']);

        $perm1 = PermissionModel::factory()->withSlug('grade.create')->create(['category_id' => $cat1->id]);
        $perm2 = PermissionModel::factory()->withSlug('payment.approve')->create(['category_id' => $cat2->id]);

        // Custom role: category_id IS NULL, slug is not owner or gestor_escuelas
        $customRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
            'slug' => 'my_custom_sp_role',
            'category_id' => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->getJson("/api/schools/{$this->school->uuid}/permissions?role_uuid={$customRole->uuid}");

        $response->assertStatus(200);

        $returnedSlugs = array_column($response->json('data'), 'slug');

        expect($returnedSlugs)->toContain('grade.create');
        expect($returnedSlugs)->toContain('payment.approve');
    });

    it('returns 404 when role does not exist', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->getJson("/api/schools/{$this->school->uuid}/permissions?role_uuid=00000000-0000-0000-0000-000000000000")
            ->assertStatus(404);
    });

    it('returns 404 when school does not exist', function () {
        $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'any_role']);

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->getJson("/api/schools/00000000-0000-0000-0000-000000000000/permissions?role_uuid={$role->uuid}")
            ->assertStatus(404);
    });

    it('returns 422 when role_uuid query param is missing', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->getJson("/api/schools/{$this->school->uuid}/permissions")
            ->assertStatus(422);
    });
});
