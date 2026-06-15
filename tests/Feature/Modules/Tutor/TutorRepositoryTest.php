<?php

use App\Common\Tenant\TenantContext;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\StudentTutor;
use App\Models\Tenant;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Modules\Tutor\Domain\Criteria\TutorListCriteria;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\ValueObjects\TutorUpdateData;
use App\Modules\Tutor\Infrastructure\Repositories\EloquentTutorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Bind TenantContext for the given tenant.
 */
function tutorRepoBind(Tenant $tenant): void
{
    app()->instance(TenantContext::class, new TenantContext(
        tenantId: $tenant->id,
        ownerId: $tenant->owner_id,
    ));
}

/**
 * Create a user scoped to the given tenant with the tutor role assigned to a school.
 *
 * @param  array<string, mixed>  $profileAttributes
 */
function tutorRepoCreateTutor(Tenant $tenant, School $school, array $profileAttributes = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_staff' => false,
        'status' => 'active',
    ]);

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

    TutorProfile::factory()->forUser($user)->create($profileAttributes);

    return $user;
}

/**
 * Create a student user scoped to the given tenant.
 */
function tutorRepoCreateStudent(Tenant $tenant, School $school): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_staff' => false,
        'status' => 'pending',
    ]);

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

describe('EloquentTutorRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        tutorRepoBind($this->tenantA);

        $this->repo = new EloquentTutorRepository(app(TenantContext::class));
    });

    // =========================================================================
    // Tenant isolation
    // =========================================================================
    describe('tenant isolation', function () {
        it('only returns tutors of the current tenant', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantB)->create();

            $tutorA = tutorRepoCreateTutor($this->tenantA, $schoolA);

            // Bind tenant B temporarily to create a tutor in tenant B
            app()->instance(TenantContext::class, new TenantContext(
                tenantId: $this->tenantB->id,
                ownerId: $this->tenantB->owner_id,
            ));
            tutorRepoCreateTutor($this->tenantB, $schoolB);

            // Re-bind tenant A and run the assertion
            tutorRepoBind($this->tenantA);
            $repo = new EloquentTutorRepository(app(TenantContext::class));

            $result = $repo->findAllPaginated(new TutorListCriteria(isOwner: true));

            $uuids = array_map(fn (Tutor $t) => $t->getUserUuid(), $result['items']);
            expect($uuids)->toContain($tutorA->uuid);
            expect(count($uuids))->toBe(1);
        });
    });

    // =========================================================================
    // School filter
    // =========================================================================
    describe('school filter', function () {
        it('filters tutors by school when requestedSchoolId is provided', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantA)->create();

            $tutorInA = tutorRepoCreateTutor($this->tenantA, $schoolA);
            $tutorInB = tutorRepoCreateTutor($this->tenantA, $schoolB);

            $result = $this->repo->findAllPaginated(
                new TutorListCriteria(requestedSchoolId: $schoolA->id, isOwner: true)
            );

            $uuids = array_map(fn (Tutor $t) => $t->getUserUuid(), $result['items']);
            expect($uuids)->toContain($tutorInA->uuid);
            expect($uuids)->not->toContain($tutorInB->uuid);
        });

        it('returns all tenant tutors when no school filter is applied', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantA)->create();

            $tutorInA = tutorRepoCreateTutor($this->tenantA, $schoolA);
            $tutorInB = tutorRepoCreateTutor($this->tenantA, $schoolB);

            $result = $this->repo->findAllPaginated(new TutorListCriteria(isOwner: true));

            $uuids = array_map(fn (Tutor $t) => $t->getUserUuid(), $result['items']);
            expect($uuids)->toContain($tutorInA->uuid);
            expect($uuids)->toContain($tutorInB->uuid);
        });
    });

    // =========================================================================
    // findByUserUuid
    // =========================================================================
    describe('findByUserUuid', function () {
        it('finds a tutor by user uuid within the current tenant', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = tutorRepoCreateTutor($this->tenantA, $school);

            $result = $this->repo->findByUserUuid($user->uuid);

            expect($result)->not->toBeNull();
            expect($result->getUserUuid())->toBe($user->uuid);
        });

        it('returns null for an unknown uuid', function () {
            $result = $this->repo->findByUserUuid('00000000-0000-0000-0000-000000000000');

            expect($result)->toBeNull();
        });

        it('returns null when the uuid belongs to a user in a different tenant', function () {
            $schoolB = School::factory()->forTenant($this->tenantB)->create();

            // Create tutor in tenant B
            $userInB = User::factory()->create([
                'tenant_id' => $this->tenantB->id,
                'is_staff' => false,
            ]);
            $tutorRole = RoleModel::firstOrCreate(
                ['slug' => 'tutor', 'tenant_id' => null],
                ['name' => 'Tutor', 'hierarchy_level' => 7, 'is_system_role' => false],
            );
            UserRoleAssignment::create([
                'user_id' => $userInB->id,
                'role_id' => $tutorRole->id,
                'school_id' => $schoolB->id,
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);
            TutorProfile::factory()->forUser($userInB)->create();

            // Repo is scoped to tenant A — should not find tenant B's tutor
            $result = $this->repo->findByUserUuid($userInB->uuid);

            expect($result)->toBeNull();
        });

        it('returns a Tutor entity (not an Eloquent model)', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = tutorRepoCreateTutor($this->tenantA, $school);

            $result = $this->repo->findByUserUuid($user->uuid);

            expect($result)->toBeInstanceOf(Tutor::class);
        });
    });

    // =========================================================================
    // create
    // =========================================================================
    describe('create', function () {
        it('creates a tutor profile linked to the user', function () {
            $user = User::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'is_staff' => false,
            ]);

            $tutor = $this->repo->create(
                userUuid: $user->uuid,
                occupation: 'Architect',
            );

            expect($tutor)->toBeInstanceOf(Tutor::class);
            expect($tutor->getOccupation())->toBe('Architect');

            $this->assertDatabaseHas('tutor_profiles', [
                'user_id' => $user->id,
                'occupation' => 'Architect',
            ]);
        });

        it('creates a tutor profile with null occupation when not provided', function () {
            $user = User::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'is_staff' => false,
            ]);

            $tutor = $this->repo->create(
                userUuid: $user->uuid,
                occupation: null,
            );

            expect($tutor->getOccupation())->toBeNull();
        });
    });

    // =========================================================================
    // update
    // =========================================================================
    describe('update', function () {
        it('persists changes and returns an updated entity', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = tutorRepoCreateTutor($this->tenantA, $school, ['occupation' => 'Teacher']);

            $tutor = $this->repo->findByUserUuid($user->uuid);
            expect($tutor)->not->toBeNull();

            $updateData = new TutorUpdateData(
                firstName: 'Nuevo',
                lastNamePaternal: 'Apellido',
                lastNameMaternal: 'Materno',
                phone: '5559876543',
                occupation: 'Engineer',
            );

            $updated = $this->repo->update($tutor->getUserId(), $updateData);

            expect($updated)->toBeInstanceOf(Tutor::class);
            expect($updated->getFirstName())->toBe('Nuevo');
            expect($updated->getOccupation())->toBe('Engineer');

            $this->assertDatabaseHas('tutor_profiles', [
                'user_id' => $user->id,
                'occupation' => 'Engineer',
            ]);
        });
    });

    // =========================================================================
    // hasActiveLink
    // =========================================================================
    describe('hasActiveLink', function () {
        it('returns true when student has at least one active tutor link', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $tutorUser = tutorRepoCreateTutor($this->tenantA, $school);
            $studentUser = tutorRepoCreateStudent($this->tenantA, $school);

            StudentTutor::create([
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => null,
                'linked_at' => now(),
                'unlinked_at' => null,
            ]);

            $result = $this->repo->hasActiveLink($studentUser->id);

            expect($result)->toBeTrue();
        });

        it('returns false when student has no active tutor links', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $studentUser = tutorRepoCreateStudent($this->tenantA, $school);

            $result = $this->repo->hasActiveLink($studentUser->id);

            expect($result)->toBeFalse();
        });

        it('returns false when student only has revoked (unlinked) tutor links', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $tutorUser = tutorRepoCreateTutor($this->tenantA, $school);
            $studentUser = tutorRepoCreateStudent($this->tenantA, $school);

            StudentTutor::create([
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => null,
                'linked_at' => now()->subDays(10),
                'unlinked_at' => now()->subDays(1),
            ]);

            $result = $this->repo->hasActiveLink($studentUser->id);

            expect($result)->toBeFalse();
        });
    });

    // =========================================================================
    // linkToStudent
    // =========================================================================
    describe('linkToStudent', function () {
        it('creates a student_tutors row linking tutor and student', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $tutorUser = tutorRepoCreateTutor($this->tenantA, $school);
            $studentUser = tutorRepoCreateStudent($this->tenantA, $school);

            $this->repo->linkToStudent($tutorUser->id, $studentUser->id, 'father');

            $this->assertDatabaseHas('student_tutors', [
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => 'father',
                'unlinked_at' => null,
            ]);
        });

        it('creates a link with null relationship when not specified', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $tutorUser = tutorRepoCreateTutor($this->tenantA, $school);
            $studentUser = tutorRepoCreateStudent($this->tenantA, $school);

            $this->repo->linkToStudent($tutorUser->id, $studentUser->id, null);

            $this->assertDatabaseHas('student_tutors', [
                'tutor_user_id' => $tutorUser->id,
                'student_user_id' => $studentUser->id,
                'relationship' => null,
            ]);
        });
    });

    // =========================================================================
    // Pagination
    // =========================================================================
    describe('pagination', function () {
        it('returns the correct pagination envelope shape', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            tutorRepoCreateTutor($this->tenantA, $school);

            $result = $this->repo->findAllPaginated(new TutorListCriteria(isOwner: true, perPage: 20, page: 1));

            expect($result)->toHaveKeys(['items', 'total', 'per_page', 'current_page', 'last_page']);
            expect($result['current_page'])->toBe(1);
            expect($result['per_page'])->toBe(20);
        });

        it('respects per_page and page parameters', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();

            for ($i = 1; $i <= 5; $i++) {
                tutorRepoCreateTutor($this->tenantA, $school);
            }

            $page1 = $this->repo->findAllPaginated(new TutorListCriteria(isOwner: true, perPage: 2, page: 1));
            $page2 = $this->repo->findAllPaginated(new TutorListCriteria(isOwner: true, perPage: 2, page: 2));

            expect(count($page1['items']))->toBe(2);
            expect($page1['current_page'])->toBe(1);
            expect($page2['current_page'])->toBe(2);
        });
    });

    // =========================================================================
    // Search filter
    // =========================================================================
    describe('search filter', function () {
        it('filters tutors by name fragment', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();

            $userA = User::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'first_name' => 'Zoila',
                'last_name_paternal' => 'Fuentes',
            ]);
            TutorProfile::factory()->forUser($userA)->create();

            $userB = User::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'first_name' => 'Roberto',
                'last_name_paternal' => 'Díaz',
            ]);
            TutorProfile::factory()->forUser($userB)->create();

            $result = $this->repo->findAllPaginated(
                new TutorListCriteria(search: 'Zoila', isOwner: true)
            );

            $uuids = array_map(fn (Tutor $t) => $t->getUserUuid(), $result['items']);
            expect($uuids)->toContain($userA->uuid);
            expect($uuids)->not->toContain($userB->uuid);
        });
    });
});
