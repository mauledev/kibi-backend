<?php

use App\Common\Staff\StaffContext;
use App\Common\Tenant\TenantContext;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Gate::before — superadmin bypass', function () {
    it('grants access when StaffContext is bound and user has system role', function () {
        $user = User::factory()->staff()->create();
        $superadmin = Role::factory()->system()->create(['slug' => 'superadmin']);
        UserRoleAssignment::factory()->forUser($user)->forRole($superadmin)->active()->create();

        app()->instance(StaffContext::class, new StaffContext);

        expect(Gate::forUser($user)->allows('any.ability'))->toBeTrue();
    });

    it('does not grant access when StaffContext is bound but user has no system role', function () {
        $user = User::factory()->staff()->create();

        app()->instance(StaffContext::class, new StaffContext);

        expect(Gate::forUser($user)->allows('any.ability'))->toBeFalse();
    });

    it('does not grant access when StaffContext is bound but user is not staff', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        app()->instance(StaffContext::class, new StaffContext);

        expect(Gate::forUser($user)->allows('any.ability'))->toBeFalse();
    });

    it('does not trigger superadmin bypass on tenant routes (TenantContext bound instead)', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->staff()->create();
        $superadmin = Role::factory()->system()->create(['slug' => 'superadmin']);
        UserRoleAssignment::factory()->forUser($user)->forRole($superadmin)->active()->create();

        // Tenant context is bound — owner bypass applies, not superadmin bypass
        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $tenant->id,
            ownerId: $tenant->owner_id,
        ));

        // Staff user is not the tenant owner — should not be granted access
        expect(Gate::forUser($user)->allows('any.ability'))->toBeFalse();
    });

    it('grants owner access on tenant routes regardless of staff status', function () {
        $tenant = Tenant::factory()->create();
        $owner = User::find($tenant->owner_id);

        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $tenant->id,
            ownerId: $tenant->owner_id,
        ));

        expect(Gate::forUser($owner)->allows('any.ability'))->toBeTrue();
    });
});
