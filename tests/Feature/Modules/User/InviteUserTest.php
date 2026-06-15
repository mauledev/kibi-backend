<?php

use App\Common\Mail\MailerInterface;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Valid invite payload.
 *
 * @return array<string, mixed>
 */
function inviteUserPayload(string $schoolUuid, string $roleUuid, string $email = 'nuevo@example.com'): array
{
    return [
        'email' => $email,
        'first_name' => 'Nuevo',
        'last_name_paternal' => 'Usuario',
        'last_name_maternal' => null,
        'assignments' => [
            ['role_uuid' => $roleUuid, 'school_uuid' => $schoolUuid],
        ],
    ];
}

describe('POST /api/users (invite)', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);
        $this->school = School::factory()->forTenant($this->tenant)->create();
        $this->role = RoleModel::factory()->forTenant($this->tenant)->create([
            'slug' => 'director',
            'name' => 'Director',
        ]);
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/tenant/users', inviteUserPayload($this->school->uuid, $this->role->uuid))
            ->assertStatus(401);
    });

    it('returns 201, creates a pending user and sends the activation email', function () {
        $this->mock(MailerInterface::class)->shouldReceive('sendActivation')->once();

        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/users', inviteUserPayload($this->school->uuid, $this->role->uuid));

        $response->assertStatus(201);
        $response->assertJsonPath('data.email', 'nuevo@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@example.com',
            'password_hash' => null,
            'email_verified_at' => null,
            'tenant_id' => $this->tenant->id,
        ]);
    });

    it('assigns the given role in the given school', function () {
        $this->mock(MailerInterface::class)->shouldReceive('sendActivation')->once();

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/users', inviteUserPayload($this->school->uuid, $this->role->uuid))
            ->assertStatus(201);

        $invited = User::where('email', 'nuevo@example.com')->firstOrFail();

        $hasRole = UserRoleAssignment::where('user_id', $invited->id)
            ->where('school_id', $this->school->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'director'))
            ->whereNull('revoked_at')
            ->exists();

        expect($hasRole)->toBeTrue();
    });

    it('returns 422 when the email already exists', function () {
        $this->mock(MailerInterface::class)->shouldReceive('sendActivation')->never();

        User::factory()->create(['email' => 'nuevo@example.com']);

        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/users', inviteUserPayload($this->school->uuid, $this->role->uuid))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'validation.unique']);
    });

    it('returns 422 when assignments are missing', function () {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/tenant/users', [
                'email' => 'x@example.com',
                'first_name' => 'X',
                'last_name_paternal' => 'Y',
            ])
            ->assertStatus(422);
    });
});
