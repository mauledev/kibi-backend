<?php

use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /school', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->school = School::factory()->forTenant($this->tenant)->create();
        $this->owner = User::find($this->tenant->owner_id);

        // Give owner a low-level role so UseCase hierarchy checks also pass.
        // Gate::before grants all gate abilities to the owner, but domain-level
        // checks still require a role assignment with a low hierarchy_level.
        $ownerFixtureRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(1)->create([
            'slug' => 'current_school_owner_fixture',
        ]);
        UserRoleAssignment::factory()
            ->forUser($this->owner)
            ->forRole($ownerFixtureRole)
            ->active()
            ->create();
    });

    it('returns 401 when unauthenticated', function () {
        $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $this->school->uuid)
            ->getJson('/api/school')
            ->assertStatus(401);
    });

    it('returns 404 when X-School-Uuid belongs to a different tenant', function () {
        $otherTenant = Tenant::factory()->create();
        $foreignSchool = School::factory()->forTenant($otherTenant)->create();

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $foreignSchool->uuid)
            ->getJson('/api/school')
            ->assertStatus(404);
    });

    it('returns 200 with school data when X-School-Uuid is valid', function () {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $this->school->uuid)
            ->getJson('/api/school');

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $this->school->uuid)
            ->assertJsonPath('data.name', $this->school->name)
            ->assertJsonPath('data.slug', $this->school->slug)
            ->assertJsonPath('data.status', $this->school->status)
            ->assertJsonStructure([
                'success',
                'status',
                'data' => ['uuid', 'name', 'slug', 'phone', 'address', 'status', 'created_at', 'updated_at'],
            ]);
    });

    it('does not expose internal id in the response', function () {
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $this->school->uuid)
            ->getJson('/api/school');

        $data = $response->assertStatus(200)->json('data');

        expect($data)->toHaveKey('uuid');
        expect($data)->not->toHaveKey('id');
    });

    it('any authenticated user can view school details regardless of role assignment to that school', function () {
        // A user with no school assignment can still hit GET /school as long as
        // SchoolMiddleware resolves the school (the endpoint has no authorization gate).
        $regularUser = User::factory()->create();
        $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
            'slug' => 'current_school_viewer_fixture',
        ]);
        UserRoleAssignment::factory()
            ->forUser($regularUser)
            ->forRole($role)
            ->active()
            ->create(['school_id' => null]);

        $response = $this->actingAs($regularUser)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $this->school->uuid)
            ->getJson('/api/school');

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $this->school->uuid);
    });

    it('returns 404 when the school identified by X-School-Uuid has been deactivated', function () {
        $deactivatedSchool = School::factory()->forTenant($this->tenant)->deactivated()->create();

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->withHeader('X-School-Uuid', $deactivatedSchool->uuid)
            ->getJson('/api/school')
            ->assertStatus(404);
    });
});
