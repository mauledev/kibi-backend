<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\Tenant;
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
function studentCtrlCreateUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->create(array_merge(
        ['tenant_id' => $tenant->id, 'is_staff' => false],
        $attributes
    ));
}

/**
 * Assign a role to a user in a school (or tenant-level when schoolId is null).
 */
function studentCtrlAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
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
function studentCtrlGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

/**
 * Create a student record: user + student profile + student role assignment.
 */
function studentCtrlCreateStudent(Tenant $tenant, School $school, array $profileAttribs = []): User
{
    $user = studentCtrlCreateUser($tenant, ['status' => 'active']);

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

    StudentProfile::factory()->forUser($user)->create($profileAttribs);

    return $user;
}

/**
 * Valid payload for creating a student.
 *
 * @return array<string, mixed>
 */
function studentCtrlCreatePayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'carlos.mendez@example.com',
        'first_name' => 'Carlos',
        'last_name_paternal' => 'Méndez',
        'last_name_maternal' => null,
        'enrollment_number' => 'ENR-001',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

describe('Student endpoints', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);
        $this->school = School::factory()->forTenant($this->tenant)->create();

        // System role required by CreateStudentUseCase::findBySlug('student')
        $this->studentRole = RoleModel::firstOrCreate(
            ['slug' => 'student', 'tenant_id' => null],
            ['name' => 'Student', 'hierarchy_level' => 9, 'is_system_role' => false],
        );
    });

    // =========================================================================
    // POST /students
    // =========================================================================
    describe('POST /api/students', function () {
        it('returns 201 with student data when owner creates a student', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload());

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

        it('returns 201 when an actor with user.create permission creates a student', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'director',
            ]);
            studentCtrlGrantPermission($role, 'user.create');
            $actor = studentCtrlCreateUser($this->tenant);
            studentCtrlAssignRole($actor, $role, $this->school->id);

            $response = $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload(['enrollment_number' => 'ENR-002']));

            $response->assertStatus(201);
        });

        it('returns 422 when required fields are missing', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', [])
                ->assertStatus(422);
        });

        it('returns 403 when actor lacks user.create permission', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_create_student_perm',
            ]);
            // No permissions granted
            $actor = studentCtrlCreateUser($this->tenant);
            studentCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload(['enrollment_number' => 'ENR-003']))
                ->assertStatus(403);
        });

        it('returns 422 when X-School-Uuid header is missing', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload())
                ->assertStatus(422);
        });

        it('returns 409 when email is already taken', function () {
            User::factory()->create(['email' => 'existing@example.com']);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload([
                    'email' => 'existing@example.com',
                ]))
                ->assertStatus(409);
        });

        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->postJson('/api/tenant/students', studentCtrlCreatePayload())
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // GET /students
    // =========================================================================
    describe('GET /api/students', function () {
        it('returns 200 with paginated list when owner requests', function () {
            studentCtrlCreateStudent($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/students');

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
            studentCtrlCreateStudent($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/students');

            $response->assertStatus(200);

            foreach ($response->json('data') as $item) {
                expect($item)->not->toHaveKey('id');
                expect($item)->toHaveKey('uuid');
            }
        });

        it('returns 403 when actor lacks user.view permission', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_view_student_perm',
            ]);
            $actor = studentCtrlCreateUser($this->tenant);
            studentCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/students')
                ->assertStatus(403);
        });

        it('filters by school when X-School-Uuid header is sent', function () {
            $schoolB = School::factory()->forTenant($this->tenant)->create();

            $studentInA = studentCtrlCreateStudent($this->tenant, $this->school);
            $studentInB = studentCtrlCreateStudent($this->tenant, $schoolB);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $this->school->uuid)
                ->getJson('/api/tenant/students');

            $response->assertStatus(200);

            $uuids = array_column($response->json('data'), 'uuid');
            expect($uuids)->toContain($studentInA->uuid);
            expect($uuids)->not->toContain($studentInB->uuid);
        });

        it('returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/students')
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // GET /students/{uuid}
    // =========================================================================
    describe('GET /api/students/{uuid}', function () {
        it('returns 200 with full student detail', function () {
            $student = studentCtrlCreateStudent($this->tenant, $this->school, [
                'enrollment_number' => 'ENR-DETAIL',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/students/{$student->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'uuid',
                        'first_name',
                        'last_name_paternal',
                        'last_name_maternal',
                        'full_name',
                        'status',
                        'enrollment_number',
                        'created_at',
                    ],
                ]);

            expect($response->json('data.uuid'))->toBe($student->uuid);
            expect($response->json('data'))->not->toHaveKey('id');
        });

        it('returns 404 for an unknown uuid', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/students/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns 404 when uuid belongs to a student in a different tenant', function () {
            $tenantB = Tenant::factory()->create();
            $schoolB = School::factory()->forTenant($tenantB)->create();

            $studentInB = studentCtrlCreateStudent($tenantB, $schoolB);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/students/{$studentInB->uuid}")
                ->assertStatus(404);
        });

        it('returns 401 when unauthenticated', function () {
            $student = studentCtrlCreateStudent($this->tenant, $this->school);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/students/{$student->uuid}")
                ->assertStatus(401);
        });
    });

    // =========================================================================
    // PUT /students/{uuid}
    // =========================================================================
    describe('PUT /api/students/{uuid}', function () {
        it('returns 200 with updated student data', function () {
            $student = studentCtrlCreateStudent($this->tenant, $this->school);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/students/{$student->uuid}", [
                    'first_name' => 'NuevoNombre',
                    'last_name_paternal' => 'Apellido',
                    'enrollment_number' => 'ENR-UPDATED',
                ]);

            $response->assertStatus(200);
            expect($response->json('data.first_name'))->toBe('NuevoNombre');
            expect($response->json('data'))->not->toHaveKey('id');
        });

        it('returns 404 for an unknown uuid', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson('/api/tenant/students/00000000-0000-0000-0000-000000000000', [
                    'first_name' => 'NuevoNombre',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(404);
        });

        it('returns 403 when actor lacks user.update permission', function () {
            $student = studentCtrlCreateStudent($this->tenant, $this->school);

            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'no_update_student_perm',
            ]);
            $actor = studentCtrlCreateUser($this->tenant);
            studentCtrlAssignRole($actor, $role, $this->school->id);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/students/{$student->uuid}", [
                    'first_name' => 'Cambiado',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(403);
        });

        it('returns 401 when unauthenticated', function () {
            $student = studentCtrlCreateStudent($this->tenant, $this->school);

            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->putJson("/api/tenant/students/{$student->uuid}", [
                    'first_name' => 'Nombre',
                    'last_name_paternal' => 'Apellido',
                ])
                ->assertStatus(401);
        });
    });
});
