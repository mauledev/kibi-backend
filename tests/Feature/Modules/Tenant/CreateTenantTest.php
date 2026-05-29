<?php

use App\Common\Mail\MailerInterface;
use App\Mail\OwnerActivationMail;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Valid payload for creating a tenant via the staff endpoint.
 *
 * @return array<string, string>
 */
function validCreateTenantPayload(): array
{
    return [
        'tenant_name' => 'Colegio San Ignacio',
        'tenant_slug' => 'san-ignacio',
        'owner_email' => 'director@sanignacio.edu.mx',
        'owner_first_name' => 'Mauricio',
        'owner_last_name_paternal' => 'Ledesma',
        'owner_last_name_maternal' => 'García',
    ];
}

describe('POST /api/staff/tenants', function () {
    beforeEach(function () {
        $this->staff = User::factory()->staff()->create();
    });

    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/staff/tenants', validCreateTenantPayload())
            ->assertStatus(401);
    });

    it('returns 201 and creates tenant with pending status', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->once();

        $response = $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'san-ignacio',
            'status' => 'pending',
        ]);

        $response->assertJsonStructure([
            'data' => [
                'uuid',
                'name',
                'slug',
                'status',
                'owner' => [
                    'uuid',
                    'email',
                    'first_name',
                    'last_name_paternal',
                ],
            ],
        ]);

        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.slug', 'san-ignacio');
        $response->assertJsonPath('data.name', 'Colegio San Ignacio');
    });

    it('creates owner user without password', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->once();

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload());

        $this->assertDatabaseHas('users', [
            'email' => 'director@sanignacio.edu.mx',
            'password_hash' => null,
            'email_verified_at' => null,
        ]);
    });

    it('assigns owner role to the created owner', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->once();

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload());

        $owner = User::where('email', 'director@sanignacio.edu.mx')->firstOrFail();

        $hasOwnerRole = UserRoleAssignment::where('user_id', $owner->id)
            ->whereHas('role', fn ($query) => $query->where('slug', 'owner'))
            ->whereNull('revoked_at')
            ->exists();

        expect($hasOwnerRole)->toBeTrue();
    });

    it('sends activation email', function () {
        Mail::fake();

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload());

        Mail::assertSent(OwnerActivationMail::class);
    });

    it('returns 409 when slug is already taken', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->never();

        Tenant::factory()->create(['slug' => 'san-ignacio']);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload())
            ->assertStatus(409);
    });

    it('returns 409 when owner email is already taken', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->never();

        User::factory()->create(['email' => 'director@sanignacio.edu.mx']);

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload())
            ->assertStatus(409);
    });

    it('returns 422 when required fields are missing', function () {
        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', [])
            ->assertStatus(422);
    });

    it('returns 422 when slug has invalid characters', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->never();

        $payload = validCreateTenantPayload();
        $payload['tenant_slug'] = 'san ignacio!';

        $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', $payload)
            ->assertStatus(422);
    });

    it('response never exposes internal id', function () {
        $this->mock(MailerInterface::class)
            ->shouldReceive('sendActivation')
            ->once();

        $response = $this->actingAs($this->staff)
            ->postJson('/api/staff/tenants', validCreateTenantPayload());

        $response->assertStatus(201);

        // Internal integer id must not appear at the data level
        expect($response->json('data.id'))->toBeNull();

        // uuid must be present and be a valid UUID
        $uuid = $response->json('data.uuid');
        expect($uuid)->toBeString();
        expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid))->toBe(1);
    });
});
