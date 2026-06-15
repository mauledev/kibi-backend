<?php

use App\Common\Mail\MailerInterface;
use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\StudentTutor;
use App\Models\Tenant;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a tenant user (non-staff, scoped by tenant_id).
 *
 * @param  array<string, mixed>  $attributes
 */
function tutorCtrlCreateUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->create(array_merge(
        ['tenant_id' => $tenant->id, 'is_staff' => false],
        $attributes
    ));
}

/**
 * Assign a role to a user in a school (or tenant-level when schoolId is null).
 */
function tutorCtrlAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

/**
 * Attach a permission with the given slug to a role.
 */
function tutorCtrlGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

/**
 * Create a tutor record: user + tutor profile + tutor role assignment.
 *
 * @param  array<string, mixed>  $profileAttribs
 */
function tutorCtrlCreateTutor(Tenant $tenant, School $school, array $profileAttribs = []): User
{
    $user = tutorCtrlCreateUser($tenant, ['status' => 'active']);

    $tutorRole = RoleModel::firstOrCreate(
        ['slug' => 'tutor', 'tenant_id' => null],
        ['name' => 'Tutor', 'hierarchy_level' => 7, 'is_system_role' => false],
    );

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $tutorRole->id,
        'school_id' => $school->id,
        'assigned_by' => null,
        'assigned_at' => now(),
        'revoked_at' => null,
    ]);

    TutorProfile::factory()->forUser($user)->create($profileAttribs);

    return $user;
}

/**
 * Create a student record: user (pending) with student role in the given school.
 */
function tutorCtrlCreateStudent(Tenant $tenant, School $school): User
{
    $user = tutorCtrlCreateUser($tenant, ['status' => 'pending']);

    $studentRole = RoleModel::firstOrCreate(
        ['slug' => 'student', 'tenant_id' => null],
        ['name' => 'Student', 'hierarchy_level' => 9, 'is_system_role' => false],
    );

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $studentRole->id,
        'school_id' => $school->id,
        'assigned_by' => null,
        'assigned_at' => now(),
        'revoked_at' => null,
    ]);

    return $user;
}

/**
 * Valid payload for creating a tutor.
 *
 * @return array<string, mixed>
 */
function tutorCtrlCreatePayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'maria.rodriguez@example.com',
        'first_name' => 'María',
        'last_name_paternal' => 'Rodríguez',
        'last_name_maternal' => null,
        'occupation' => null,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

describe('Tutor endpoints', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);
        $this->school = School::factory()->forTenant($this->tenant)->create();

        // System role required by CreateTutorUseCase::findBySlug('tutor')
        $this->tutorRole = RoleModel::firstOrCreate(
            ['slug' => 'tutor', 'tenant_id' => null],
            ['name' => 'Tutor', 'hierarchy_level' => 7, 'is_system_role' => false],
        );
    });

    // =========================================================================
    // GET /tutors
    // =========================================================================
    describe('GET /api/tutors', function () {
        it('returns 200 with paginated list when owner requests', function () {
            tutorCtrlCreateTutor($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/tutors');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => [
                        'pagination' => [
                            'total',
                            'per_page',
                            'current_page',
                            'last_page',
                        ],
                    ],
                ]);
        });

        it('does not expose internal id in list items', function () {
            tutorCtrlCreateTutor($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/tutors');

            $response->assertStatus(200);

            foreach ($response->json('data') as $item) {
                expect($item)->not->toHaveKey('id');
                expect($item)->toHaveKey('uuid');
            }
        });

        it('returns 403 when actor lacks user.view permission', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_view_tutor_perm',
            ]);
            $actor = tutorCtrlCreateUser($this->tenant);
            tutorCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/tutors')
                ->assertStatus(403);
        });

        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/tutors')
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // GET /tutors/{uuid}
    // =========================================================================
    describe('GET /api/tutors/{uuid}', function () {
        it('returns 200 with full tutor detail', function () {
            $tutor = tutorCtrlCreateTutor($this->tenant, $this->school, [
                'occupation' => 'Engineer',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/tutors/{$tutor->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'uuid',
                        'first_name',
                        'last_name_paternal',
                        'last_name_maternal',
                        'full_name',
                        'status',
                        'occupation',
                        'created_at',
                    ],
                ]);

            expect($response->json('data.uuid'))->toBe($tutor->uuid);
            expect($response->json('data'))->not->toHaveKey('id');
        });

        it('returns 404 for an unknown uuid', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/tutors/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns 404 when uuid belongs to a tutor in a different tenant', function () {
            $tenantB = Tenant::factory()->create();
            $schoolB = School::factory()->forTenant($tenantB)->create();

            $tutorInB = tutorCtrlCreateTutor($tenantB, $schoolB);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/tutors/{$tutorInB->uuid}")
                ->assertStatus(404);
        });

        it('returns 401 when unauthenticated', function () {
            $tutor = tutorCtrlCreateTutor($this->tenant, $this->school);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/tutors/{$tutor->uuid}")
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // POST /tutors
    // =========================================================================
    describe('POST /api/tutors', function () {
        it('returns 201 with tutor data and sends magic link when owner creates a tutor', function () {
            $this->mock(MailerInterface::class)
                ->shouldReceive('sendActivation')
                ->once();

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload());

            $response->assertStatus(201);
            $response->assertJsonPath('success', true);
            $response->assertJsonStructure([
                'data' => [
                    'uuid',
                    'first_name',
                    'last_name_paternal',
                    'full_name',
                    'status',
                ],
            ]);
            expect($response->json('data'))->not->toHaveKey('id');
        });

        it('returns 201 when an actor with user.create permission creates a tutor', function () {
            $this->mock(MailerInterface::class)
                ->shouldReceive('sendActivation')
                ->once();

            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'director',
            ]);
            tutorCtrlGrantPermission($role, 'user.create');
            $actor = tutorCtrlCreateUser($this->tenant);
            tutorCtrlAssignRole($actor, $role, $this->school->id);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload([
                    'email' => 'tutor.director@example.com',
                ]));

            $response->assertStatus(201);
        });

        it('returns 422 when required fields are missing', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', [])
                ->assertStatus(422);
        });

        it('returns 403 when actor lacks user.create permission', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_create_tutor_perm',
            ]);
            // No permissions granted
            $actor = tutorCtrlCreateUser($this->tenant);
            tutorCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload([
                    'email' => 'forbidden.tutor@example.com',
                ]))
                ->assertStatus(403);
        });

        it('returns 422 when X-School-Uuid header is missing', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload())
                ->assertStatus(422);
        });

        it('returns 409 when email is already taken', function () {
            User::factory()->create(['email' => 'existing.tutor@example.com']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload([
                    'email' => 'existing.tutor@example.com',
                ]))
                ->assertStatus(409);
        });

        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/tutors', tutorCtrlCreatePayload())
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // PUT /tutors/{uuid}
    // =========================================================================
    describe('PUT /api/tutors/{uuid}', function () {
        it('returns 200 with updated tutor data', function () {
            $tutor = tutorCtrlCreateTutor($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/tutors/{$tutor->uuid}", [
                    'first_name' => 'NuevoNombre',
                    'last_name_paternal' => 'Apellido',
                    'occupation' => 'Doctor',
                ]);

            $response->assertStatus(200);
            expect($response->json('data.first_name'))->toBe('NuevoNombre');
            expect($response->json('data'))->not->toHaveKey('id');
        });

        it('returns 404 for an unknown uuid', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson('/api/tenant/tutors/00000000-0000-0000-0000-000000000000', [
                    'first_name' => 'NuevoNombre',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(404);
        });

        it('returns 403 when actor lacks user.update permission', function () {
            $tutor = tutorCtrlCreateTutor($this->tenant, $this->school);

            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_update_tutor_perm',
            ]);
            $actor = tutorCtrlCreateUser($this->tenant);
            tutorCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/tutors/{$tutor->uuid}", [
                    'first_name' => 'Cambiado',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(403);
        });

        it('returns 401 when unauthenticated', function () {
            $tutor = tutorCtrlCreateTutor($this->tenant, $this->school);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/tutors/{$tutor->uuid}", [
                    'first_name' => 'Nombre',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // POST /tutors/{tutorUuid}/students/{studentUuid}
    // =========================================================================
    describe('POST /api/tutors/{tutorUuid}/students/{studentUuid}', function () {
        it('returns 200 and links the tutor to student, sending magic link when student has no prior link', function () {
            $this->mock(MailerInterface::class)
                ->shouldReceive('sendActivation')
                ->once();

            $tutorUser = tutorCtrlCreateTutor($this->tenant, $this->school);
            $studentUser = tutorCtrlCreateStudent($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/tutors/{$tutorUser->uuid}/students/{$studentUser->uuid}", [
                    'relationship' => 'mother',
                ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('student_tutors', [
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => 'mother',
                'unlinked_at' => null,
            ]);
        });

        it('does NOT send magic link when student already has another active tutor link', function () {
            // The mailer must NOT receive sendActivation at all
            $this->mock(MailerInterface::class)
                ->shouldNotReceive('sendActivation');

            $existingTutor = tutorCtrlCreateTutor($this->tenant, $this->school);
            $newTutor = tutorCtrlCreateTutor($this->tenant, $this->school);
            $studentUser = tutorCtrlCreateStudent($this->tenant, $this->school);

            // Student already has an active link with existingTutor
            StudentTutor::create([
                'tutor_user_id' => $existingTutor->id,
                'student_user_id' => $studentUser->id,
                'relationship' => null,
                'linked_at' => now(),
                'unlinked_at' => null,
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/tutors/{$newTutor->uuid}/students/{$studentUser->uuid}", [
                    'relationship' => 'father',
                ]);

            $response->assertStatus(200);
        });

        it('returns 409 when the same tutor-student pair is already actively linked', function () {
            $tutorUser = tutorCtrlCreateTutor($this->tenant, $this->school);
            $studentUser = tutorCtrlCreateStudent($this->tenant, $this->school);

            // Active link already exists
            StudentTutor::create([
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => null,
                'linked_at' => now(),
                'unlinked_at' => null,
            ]);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/tutors/{$tutorUser->uuid}/students/{$studentUser->uuid}", [])
                ->assertStatus(409);
        });

        it('returns 404 when tutor is not found', function () {
            $studentUser = tutorCtrlCreateStudent($this->tenant, $this->school);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/tutors/00000000-0000-0000-0000-000000000000/students/'.$studentUser->uuid, [])
                ->assertStatus(404);
        });

        it('returns 404 when student is not found', function () {
            $tutorUser = tutorCtrlCreateTutor($this->tenant, $this->school);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/tutors/{$tutorUser->uuid}/students/00000000-0000-0000-0000-000000000000", [])
                ->assertStatus(404);
        });

        it('returns 401 when unauthenticated', function () {
            $tutorUser = tutorCtrlCreateTutor($this->tenant, $this->school);
            $studentUser = tutorCtrlCreateStudent($this->tenant, $this->school);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson("/api/tenant/tutors/{$tutorUser->uuid}/students/{$studentUser->uuid}", [])
                ->assertStatus(401);
        });
    });
});
