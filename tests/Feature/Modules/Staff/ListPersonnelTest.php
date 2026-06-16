<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/staff/personnel', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Superadmin staff user (the only one authorized to list personnel).
        $this->superadmin = User::factory()->staff()->create();

        $role = Role::where('slug', 'superadmin')->whereNull('tenant_id')->firstOrFail();
        UserRoleAssignment::create([
            'user_id' => $this->superadmin->id,
            'role_id' => $role->id,
            'school_id' => null,
            'assigned_at' => now(),
        ]);

        acceptPurFor($this->superadmin);
    });

    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/staff/personnel')->assertStatus(401);
    });

    it('returns 403 when the staff user is not superadmin', function () {
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($plainStaff)
            ->getJson('/api/staff/personnel')
            ->assertStatus(403);
    });

    it('returns 200 with a paginated envelope', function () {
        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/staff/personnel');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'first_name', 'last_name_paternal', 'full_name', 'email', 'role', 'status', 'created_at'],
                ],
                'meta' => [
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);
    });

    it('limits results to per_page and reports the pagination meta', function () {
        // The superadmin is 1 staff user; add 4 more → 5 total.
        User::factory()->staff()->count(4)->create();

        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/staff/personnel?per_page=2&page=1');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(2);
        $response->assertJsonPath('meta.pagination.total', 5);
        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.current_page', 1);
        $response->assertJsonPath('meta.pagination.last_page', 3);
    });

    it('serves the requested page', function () {
        User::factory()->staff()->count(4)->create();

        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/staff/personnel?per_page=2&page=3');

        $response->assertStatus(200);
        // 5 staff users over per_page=2 → page 3 holds the last (1) item.
        expect($response->json('data'))->toHaveCount(1);
        $response->assertJsonPath('meta.pagination.current_page', 3);
    });

    it('clamps per_page to a maximum of 100', function () {
        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/staff/personnel?per_page=9999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.pagination.per_page', 100);
    });

    it('excludes non-staff (tenant) users from the list', function () {
        User::factory()->create(); // tenant user (is_staff = false)

        $response = $this->actingAs($this->superadmin)
            ->getJson('/api/staff/personnel?per_page=100');

        $response->assertStatus(200);
        // Only the superadmin staff user is present.
        expect($response->json('data'))->toHaveCount(1);
        $response->assertJsonPath('meta.pagination.total', 1);
    });
});
