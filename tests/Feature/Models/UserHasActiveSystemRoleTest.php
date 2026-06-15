<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User::hasActiveSystemRole()', function () {
    it('returns false when user has no role assignments', function () {
        $user = User::factory()->staff()->create();

        expect($user->hasActiveSystemRole())->toBeFalse();
    });

    it('returns false when user only has non-system roles', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $role = Role::factory()->for($tenant)->create(['is_system_role' => false]);

        UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

        expect($user->hasActiveSystemRole())->toBeFalse();
    });

    it('returns true when user has an active system role assignment', function () {
        $user = User::factory()->staff()->create();
        $systemRole = Role::factory()->system()->create(['slug' => 'superadmin']);

        UserRoleAssignment::factory()->forUser($user)->forRole($systemRole)->active()->create();

        expect($user->hasActiveSystemRole())->toBeTrue();
    });

    it('returns false when the system role assignment is revoked', function () {
        $user = User::factory()->staff()->create();
        $systemRole = Role::factory()->system()->create(['slug' => 'superadmin']);

        UserRoleAssignment::factory()->forUser($user)->forRole($systemRole)->create([
            'revoked_at' => now()->subDay(),
        ]);

        expect($user->hasActiveSystemRole())->toBeFalse();
    });
});
