<?php

use App\Mail\OwnerActivationMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Valid payload for creating Backoffice staff personnel.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validCreatePersonnelPayload(array $overrides = []): array
{
    $base = [
        'role' => 'operator',
        'personal_data' => [
            'first_name' => 'Karla',
            'last_name_paternal' => 'Mendoza',
            'last_name_maternal' => null,
            'email' => 'karla.mendoza@softlinkia.com',
            'phone' => null,
        ],
        'work_schedule' => [
            'timezone' => 'America/Mexico_City',
            'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => '09:00',
            'end_time' => '18:00',
        ],
        'permissions' => ['billing.approve', 'billing.view', 'remittance.create'],
    ];

    // Shallow-merge the nested objects so partial overrides keep sibling fields,
    // while list fields (permissions, days) are replaced wholesale.
    foreach (['personal_data', 'work_schedule'] as $key) {
        if (isset($overrides[$key])) {
            $overrides[$key] = array_merge($base[$key], $overrides[$key]);
        }
    }

    return array_merge($base, $overrides);
}

/** Grant the Softlinkia superadmin role to a staff user. */
function makePersonnelSuperadmin(User $user): void
{
    $role = Role::where('slug', 'superadmin')->whereNull('tenant_id')->firstOrFail();

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'school_id' => null,
        'assigned_at' => now(),
    ]);

    acceptPurFor($user);
}

describe('POST /api/staff/personnel', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();

        $this->superadmin = User::factory()->staff()->create();
        makePersonnelSuperadmin($this->superadmin);
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(401);
    });

    it('returns 403 when the staff user is not superadmin', function () {
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($plainStaff)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'karla.mendoza@softlinkia.com']);
    });

    it('returns 201 and creates a pending staff user', function () {
        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'karla.mendoza@softlinkia.com',
            'is_staff' => true,
            'tenant_id' => null,
            'password_hash' => null,
            'email_verified_at' => null,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'uuid',
                'role',
                'personal_data' => ['first_name', 'last_name_paternal', 'email', 'phone'],
                'work_schedule' => ['timezone', 'days', 'start_time', 'end_time'],
                'permissions',
                'requires_2fa',
                'created_at',
            ],
        ]);

        $response->assertJsonPath('data.role', 'operator');
        $response->assertJsonPath('data.requires_2fa', false);
    });

    it('assigns the staff role to the created user', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(201);

        $created = User::where('email', 'karla.mendoza@softlinkia.com')->firstOrFail();

        $hasRole = UserRoleAssignment::where('user_id', $created->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'operator'))
            ->whereNull('revoked_at')
            ->exists();

        expect($hasRole)->toBeTrue();
    });

    it('persists the work schedule', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(201);

        $created = User::where('email', 'karla.mendoza@softlinkia.com')->firstOrFail();

        $this->assertDatabaseHas('staff_work_schedules', [
            'user_id' => $created->id,
            'timezone' => 'America/Mexico_City',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
    });

    it('derives requires_2fa from the role (leader requires 2FA)', function () {
        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload([
                'role' => 'leader',
                'personal_data' => ['email' => 'lider@softlinkia.com'],
                'permissions' => ['billing.view'],
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.requires_2fa', true);
    });

    it('applies denials for unchecked permissions (effective = selected subset)', function () {
        // operator defaults are 3 permissions; we only keep one.
        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload([
                'permissions' => ['billing.view'],
            ]));

        $response->assertStatus(201);
        expect($response->json('data.permissions'))->toBe(['billing.view']);
    });

    it('sends an activation email', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(201);

        Mail::assertSent(OwnerActivationMail::class);
    });

    it('returns 409 when the email is already taken', function () {
        User::factory()->create(['email' => 'karla.mendoza@softlinkia.com']);

        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload())
            ->assertStatus(409);

        Mail::assertNothingSent();
    });

    it('returns 422 for an unknown role', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload(['role' => 'ghost']))
            ->assertStatus(422);
    });

    it('returns 422 for a permission outside the role catalogue', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload([
                'permissions' => ['totally.invalid'],
            ]))
            ->assertStatus(422);
    });

    it('returns 422 for an invalid work schedule', function () {
        $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload([
                'work_schedule' => ['days' => ['funday'], 'start_time' => '9am'],
            ]))
            ->assertStatus(422);
    });

    it('never exposes the internal id', function () {
        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/staff/personnel', validCreatePersonnelPayload());

        $response->assertStatus(201);

        $id = $response->json('data.uuid');
        expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id))->toBe(1);
    });
});
