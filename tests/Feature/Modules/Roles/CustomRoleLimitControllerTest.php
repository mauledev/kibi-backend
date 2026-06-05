<?php

use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function limitAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

describe('PUT /api/tenant/custom-roles-limit', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);

        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'limit_owner_fixture',
        ]);
        limitAssignRole($this->owner, $ownerFixtureRole);
    });

    it('returns 403 when actor is not the owner', function () {
        $nonOwner = User::factory()->create();
        $nonOwnerRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(4)->create([
            'slug' => 'director_limit_test',
        ]);
        limitAssignRole($nonOwner, $nonOwnerRole);

        $this->actingAs($nonOwner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 10])
            ->assertStatus(403);
    });

    it('returns 422 when limit is 0 (below minimum)', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 0])
            ->assertStatus(422);
    });

    it('returns 422 when limit is 51 (above maximum)', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 51])
            ->assertStatus(422);
    });

    it('returns 422 when limit is missing', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', [])
            ->assertStatus(422);
    });

    it('returns 200 and updates the limit when owner sets it to 10', function () {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 10]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'custom_roles_limit' => 10,
        ]);
    });

    it('returns 200 and sets limit at boundary value 1', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 1])
            ->assertStatus(200);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'custom_roles_limit' => 1,
        ]);
    });

    it('returns 200 and sets limit at boundary value 50', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 50])
            ->assertStatus(200);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'custom_roles_limit' => 50,
        ]);
    });

    it('creates an audit_log entry on successful update', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->putJson('/api/tenant/custom-roles-limit', ['limit' => 10]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.custom_roles_limit.update',
            'user_id' => $this->owner->id,
        ]);
    });
});
