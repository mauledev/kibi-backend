<?php

use App\Models\Role as RoleModel;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Modules\Schools\Domain\Enums\SchoolListFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assignSchoolRole(User $user, RoleModel $role): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create();
}

describe('SchoolController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create(['slug' => 'kibi-test']);
        $this->otherTenant = Tenant::factory()->create(['slug' => 'other-test']);
        $this->owner = User::find($this->tenant->owner_id);
    });

    describe('GET /api/tenant/schools', function () {
        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools')
                ->assertStatus(401);
        });

        it('returns 403 when user has no role with school.view permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            // No roles assigned — no permissions

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools')
                ->assertStatus(403);
        });

        it('owner bypasses permission check and receives 200', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools')
                ->assertStatus(200);
        });

        it('returns 200 with the expected API envelope', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'status', 'data']);
        });

        it('returns schools for the current tenant only', function () {
            SchoolModel::factory()->for($this->tenant)->create(['name' => 'My School']);
            SchoolModel::factory()->for($this->otherTenant)->create(['name' => 'Other School']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $response->assertStatus(200);

            $names = array_column($response->json('data'), 'name');

            expect($names)->toContain('My School');
            expect($names)->not->toContain('Other School');
        });

        it('returns response objects with uuid and never internal id', function () {
            SchoolModel::factory()->for($this->tenant)->create();

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $response->assertStatus(200);

            $item = $response->json('data.0');

            expect($item)->toHaveKey('uuid');
            expect($item)->not->toHaveKey('id');

            // uuid must match the UUID v4 format
            expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $item['uuid']))->toBe(1);
        });

        it('returns the expected response shape per school object', function () {
            SchoolModel::factory()->for($this->tenant)->create();

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'uuid',
                            'name',
                            'slug',
                            'phone',
                            'address',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ]);
        });

        it('returns an empty data array when the tenant has no schools', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $response->assertStatus(200);

            expect($response->json('data'))->toBeArray()->toBeEmpty();
        });

        describe('?status filter', function () {
            beforeEach(function () {
                $this->user = $this->owner;
            });

            it('without status param it defaults to Active (excludes suspended and soft-deleted)', function () {
                SchoolModel::factory()->for($this->tenant)->create(['status' => 'active']);
                SchoolModel::factory()->for($this->tenant)->suspended()->create();
                SchoolModel::factory()->for($this->tenant)->deactivated()->create();

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools');

                $response->assertStatus(200);
                expect($response->json('data'))->toHaveCount(1);
                expect($response->json('data.0.status'))->toBe('active');
                expect($response->json('data.0.deleted_at'))->toBeNull();
            });

            it('returns 200 and only active schools when status=active', function () {
                SchoolModel::factory()->for($this->tenant)->create(['status' => 'active']);
                SchoolModel::factory()->for($this->tenant)->suspended()->create();

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status='.SchoolListFilter::Active->value);

                $response->assertStatus(200);
                $statuses = array_column($response->json('data'), 'status');
                expect($statuses)->each->toBe('active');
            });

            it('returns 200 and only soft-deleted schools when status=deactivated', function () {
                SchoolModel::factory()->for($this->tenant)->create(['status' => 'active']);
                SchoolModel::factory()->for($this->tenant)->deactivated()->create();

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status='.SchoolListFilter::Deactivated->value);

                $response->assertStatus(200);
                expect($response->json('data'))->toHaveCount(1);
                expect($response->json('data.0.deleted_at'))->not->toBeNull();
            });

            it('returns 200 with all schools (including soft-deleted) when status=all', function () {
                SchoolModel::factory()->for($this->tenant)->create(['status' => 'active']);
                SchoolModel::factory()->for($this->tenant)->suspended()->create();
                SchoolModel::factory()->for($this->tenant)->deactivated()->create();

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status='.SchoolListFilter::All->value);

                $response->assertStatus(200);
                expect($response->json('data'))->toHaveCount(3);
            });

            it('returns 422 with a validation error when status=suspended (no longer in filter)', function () {
                $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status=suspended')
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['status']);
            });

            it('returns 422 with a validation error on status field when an invalid value is passed', function () {
                $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status=foo')
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['status']);
            });

            it('includes deleted_at as null for non-deleted schools in the resource', function () {
                SchoolModel::factory()->for($this->tenant)->create(['status' => 'active']);

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools');

                $response->assertStatus(200);
                expect($response->json('data.0.deleted_at'))->toBeNull();
            });

            it('includes deleted_at as an ISO 8601 string for deactivated schools in the resource', function () {
                SchoolModel::factory()->for($this->tenant)->deactivated()->create();

                $response = $this->actingAs($this->user)
                    ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                    ->getJson('/api/tenant/schools?status='.SchoolListFilter::Deactivated->value);

                $response->assertStatus(200);
                $deletedAt = $response->json('data.0.deleted_at');
                expect($deletedAt)->toBeString()->not->toBeEmpty();
                // ISO 8601 basic check: contains a T separator
                expect(str_contains($deletedAt, 'T'))->toBeTrue();
            });
        });
    });

    describe('GET /api/tenant/schools/{uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$school->uuid}")
                ->assertStatus(401);
        });

        it('returns 403 when user has no role with school.view permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$school->uuid}")
                ->assertStatus(403);
        });

        it('returns 200 with the school for the current tenant', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create(['name' => 'Target']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$school->uuid}");

            $response->assertStatus(200)
                ->assertJsonPath('data.uuid', $school->uuid)
                ->assertJsonPath('data.name', 'Target')
                ->assertJsonStructure([
                    'data' => ['uuid', 'name', 'slug', 'phone', 'address', 'status', 'created_at', 'updated_at'],
                ]);
        });

        it('returns 404 when uuid does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns 404 when uuid belongs to another tenant', function () {
            $foreign = SchoolModel::factory()->for($this->otherTenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$foreign->uuid}")
                ->assertStatus(404);
        });

        it('does not expose internal id', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$school->uuid}");

            $data = $response->json('data');

            expect($data)->toHaveKey('uuid');
            expect($data)->not->toHaveKey('id');
        });
    });

    describe('POST /api/tenant/schools', function () {
        $validPayload = fn (): array => [
            'name' => 'Colegio Nuevo',
            'slug' => 'colegio-nuevo',
            'phone' => '+52 55 1234 5678',
            'address' => [
                'street' => 'Av. Reforma',
                'exterior_number' => '100',
                'neighborhood' => 'Centro',
                'municipality' => 'CDMX',
                'state' => 'CDMX',
                'postal_code' => '06000',
                'country' => 'MX',
            ],
        ];

        it('returns 401 when unauthenticated', function () use ($validPayload) {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload())
                ->assertStatus(401);
        });

        it('returns 403 when user has no school.create permission', function () use ($validPayload) {
            $user = User::factory()->for($this->tenant)->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload())
                ->assertStatus(403);
        });

        it('returns 201 with the created school for owner', function () use ($validPayload) {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload());

            $response->assertStatus(201)
                ->assertJsonPath('data.name', 'Colegio Nuevo')
                ->assertJsonPath('data.slug', 'colegio-nuevo')
                ->assertJsonPath('data.status', 'active')
                ->assertJsonPath('data.address.street', 'Av. Reforma')
                ->assertJsonStructure([
                    'data' => ['uuid', 'name', 'slug', 'phone', 'address', 'status', 'created_at', 'updated_at'],
                ]);
        });

        it('persists the school under the current tenant', function () use ($validPayload) {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload())
                ->assertStatus(201);

            $this->assertDatabaseHas('schools', [
                'tenant_id' => $this->tenant->id,
                'slug' => 'colegio-nuevo',
                'status' => 'active',
            ]);
        });

        it('returns 409 when slug already exists within the tenant', function () use ($validPayload) {
            SchoolModel::factory()->for($this->tenant)->create(['slug' => 'colegio-nuevo']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload())
                ->assertStatus(409);
        });

        it('allows the same slug across different tenants', function () use ($validPayload) {
            // Same slug already taken in another tenant — must not conflict.
            SchoolModel::factory()->for($this->otherTenant)->create(['slug' => 'colegio-nuevo']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', $validPayload())
                ->assertStatus(201);
        });

        it('returns 422 on missing required fields', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', ['phone' => '+52'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'slug']);
        });

        it('returns 422 on invalid slug format', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools', ['name' => 'X', 'slug' => 'NOT VALID SLUG'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
        });
    });

    describe('PUT /api/tenant/schools/{uuid}', function () {
        it('returns 401 when unauthenticated', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['name' => 'X'])
                ->assertStatus(401);
        });

        it('returns 403 when user has no school.update permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['name' => 'X'])
                ->assertStatus(403);
        });

        it('returns 200 and persists changes for owner', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create([
                'name' => 'Before',
                'phone' => '+52 00 00',
            ]);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", [
                    'name' => 'After',
                    'phone' => '+52 55 9999 9999',
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'After')
                ->assertJsonPath('data.phone', '+52 55 9999 9999');

            $this->assertDatabaseHas('schools', [
                'id' => $school->id,
                'name' => 'After',
                'phone' => '+52 55 9999 9999',
            ]);
        });

        it('leaves omitted fields untouched (partial update)', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create([
                'name' => 'Untouched',
                'phone' => '+52 00 00',
            ]);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['phone' => '+52 55 1111 1111'])
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'Untouched')
                ->assertJsonPath('data.phone', '+52 55 1111 1111');
        });

        it('allows clearing phone to null', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create(['phone' => '+52 00 00']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['phone' => null])
                ->assertStatus(200)
                ->assertJsonPath('data.phone', null);
        });

        it('ignores slug when present in the payload', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create(['slug' => 'original']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['slug' => 'hacked'])
                ->assertStatus(200)
                ->assertJsonPath('data.slug', 'original');
        });

        it('returns 404 when uuid does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson('/api/tenant/schools/00000000-0000-0000-0000-000000000000', ['name' => 'X'])
                ->assertStatus(404);
        });

        it('returns 404 when uuid belongs to another tenant', function () {
            $foreign = SchoolModel::factory()->for($this->otherTenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$foreign->uuid}", ['name' => 'X'])
                ->assertStatus(404);
        });

        it('returns 422 when name is empty string', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/schools/{$school->uuid}", ['name' => ''])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('POST /api/tenant/schools/{uuid}/deactivate', function () {
        it('returns 401 when unauthenticated', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(401);
        });

        it('returns 403 when user has no school.update permission', function () {
            $user = User::factory()->for($this->tenant)->create();
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(403);
        });

        it('returns 200 and soft-deletes the school', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(200)
                ->assertJsonPath('data', null);

            $this->assertSoftDeleted('schools', ['id' => $school->id]);
        });

        it('soft-deletes a suspended school the same way', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create(['status' => 'suspended']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(200);

            $this->assertSoftDeleted('schools', ['id' => $school->id]);
        });

        it('makes the school disappear from GET /schools', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(200);

            $listResponse = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/schools');

            $uuids = array_column($listResponse->json('data'), 'uuid');
            expect($uuids)->not->toContain($school->uuid);
        });

        it('returns 404 on subsequent GET of the deactivated school', function () {
            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(200);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/schools/{$school->uuid}")
                ->assertStatus(404);
        });

        it('returns 404 when deactivating an already-deactivated school', function () {
            $school = SchoolModel::factory()->for($this->tenant)->deactivated()->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$school->uuid}/deactivate")
                ->assertStatus(404);
        });

        it('returns 404 when uuid does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/schools/00000000-0000-0000-0000-000000000000/deactivate')
                ->assertStatus(404);
        });

        it('returns 404 when uuid belongs to another tenant', function () {
            $foreign = SchoolModel::factory()->for($this->otherTenant)->create();

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/schools/{$foreign->uuid}/deactivate")
                ->assertStatus(404);

            $this->assertDatabaseHas('schools', [
                'id' => $foreign->id,
                'deleted_at' => null,
            ]);
        });
    });
});
