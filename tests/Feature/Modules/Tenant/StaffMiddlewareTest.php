<?php

use App\Common\Staff\StaffContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('StaffMiddleware', function () {
    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/staff/tenants')
            ->assertStatus(401);
    });

    it('returns 403 when authenticated as a tenant user', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->getJson('/api/staff/tenants')
            ->assertStatus(403);
    });

    it('returns 403 when authenticated as a tenant owner', function () {
        $tenant = Tenant::factory()->create();
        $owner = User::find($tenant->owner_id);

        $this->actingAs($owner)
            ->getJson('/api/staff/tenants')
            ->assertStatus(403);
    });

    it('passes through when authenticated as a staff user', function () {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->getJson('/api/staff/tenants')
            ->assertStatus(200);
    });

    it('binds StaffContext for staff requests', function () {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->getJson('/api/staff/tenants');

        expect(app()->bound(StaffContext::class))->toBeTrue();
    });

    it('does not bind StaffContext on tenant routes', function () {
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/health');

        expect(app()->bound(StaffContext::class))->toBeFalse();
    });
});
