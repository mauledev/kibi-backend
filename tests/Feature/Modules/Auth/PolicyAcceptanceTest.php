<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserPolicyAcceptance;
use App\Models\UserRoleAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Unique helper names — Pest test files declare global functions and other
 * suites already define makeSuperadmin()/makeApprovalSuperadmin().
 */
function makePurStaff(string $email, string $roleSlug): User
{
    $user = User::factory()->staff()->create(['email' => $email]);

    $role = Role::where('slug', $roleSlug)->whereNull('tenant_id')->firstOrFail();

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'school_id' => null,
        'assigned_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('Responsible Use Policy gate', function () {
    it('flags a superadmin who has not accepted and blocks app endpoints', function () {
        $superadmin = makePurStaff('pur-sa@softlinkia.com', 'superadmin');

        // /me exposes the flag and stays reachable behind the gate.
        $this->actingAs($superadmin)
            ->getJson('/api/staff/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.must_accept_policy', true);

        // A superadmin-only app endpoint: the OUTER policy gate fires before
        // staff.superadmin, so the policy 403 wins.
        $this->actingAs($superadmin)
            ->getJson('/api/staff/personnel')
            ->assertStatus(403)
            ->assertJsonPath('errors.policy', ['ACCEPTANCE_REQUIRED']);

        // A non-superadmin app endpoint is gated too.
        $this->actingAs($superadmin)
            ->getJson('/api/staff/tenants')
            ->assertStatus(403)
            ->assertJsonPath('errors.policy', ['ACCEPTANCE_REQUIRED']);
    });

    it('records + audits the acceptance and lifts the gate', function () {
        $superadmin = makePurStaff('pur-sa@softlinkia.com', 'superadmin');

        $this->actingAs($superadmin)
            ->postJson('/api/staff/auth/policy/accept')
            ->assertStatus(200);

        $this->assertDatabaseHas('user_policy_acceptances', [
            'user_id' => $superadmin->id,
            'policy_type' => 'pur',
            'version' => '1.0',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'policy.accepted',
            'user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin)
            ->getJson('/api/staff/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.must_accept_policy', false);

        // Gate lifted → the superadmin reaches the endpoint (passes staff.superadmin too).
        $this->actingAs($superadmin)
            ->getJson('/api/staff/personnel')
            ->assertStatus(200);
    });

    it('is idempotent: accepting twice keeps a single row', function () {
        $superadmin = makePurStaff('pur-sa@softlinkia.com', 'superadmin');

        $this->actingAs($superadmin)->postJson('/api/staff/auth/policy/accept')->assertStatus(200);
        $this->actingAs($superadmin)->postJson('/api/staff/auth/policy/accept')->assertStatus(200);

        expect(UserPolicyAcceptance::where('user_id', $superadmin->id)->count())->toBe(1);
    });

    it('never flags nor policy-blocks a role that does not require the policy', function () {
        $operator = makePurStaff('pur-op@softlinkia.com', 'operator');

        $this->actingAs($operator)
            ->getJson('/api/staff/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.must_accept_policy', false);

        // Not blocked by the policy gate (may still be limited by other authz,
        // but never the POLICY 403).
        $this->actingAs($operator)
            ->getJson('/api/staff/tenants')
            ->assertJsonMissingPath('errors.policy');

        expect(DB::table('user_policy_acceptances')->where('user_id', $operator->id)->count())->toBe(0);
    });
});
