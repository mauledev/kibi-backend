<?php

use App\Common\Tenant\TenantContext;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Modules\Student\Domain\Criteria\StudentListCriteria;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Infrastructure\Repositories\EloquentStudentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Bind TenantContext for the given tenant.
 */
function studentRepoBind(Tenant $tenant): void
{
    app()->instance(TenantContext::class, new TenantContext(
        tenantId: $tenant->id,
        ownerId: $tenant->owner_id,
    ));
}

/**
 * Create a user scoped to the given tenant with the student role assigned to a school.
 */
function studentRepoCreateStudent(Tenant $tenant, School $school, array $profileAttributes = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_staff' => false,
        'status' => 'active',
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

    StudentProfile::factory()->forUser($user)->create($profileAttributes);

    return $user;
}

describe('EloquentStudentRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        studentRepoBind($this->tenantA);

        $this->repo = new EloquentStudentRepository(app(TenantContext::class));
    });

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------
    describe('tenant isolation', function () {
        it('only returns students of the current tenant', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantB)->create();

            $studentA = studentRepoCreateStudent($this->tenantA, $schoolA);

            // Bind tenant B temporarily to create a student in tenant B
            app()->instance(TenantContext::class, new TenantContext(
                tenantId: $this->tenantB->id,
                ownerId: $this->tenantB->owner_id,
            ));
            studentRepoCreateStudent($this->tenantB, $schoolB);

            // Re-bind tenant A and run the assertion
            studentRepoBind($this->tenantA);
            $repo = new EloquentStudentRepository(app(TenantContext::class));

            $result = $repo->findAllPaginated(new StudentListCriteria(isOwner: true));

            $uuids = array_map(fn (Student $s) => $s->getUserUuid(), $result['items']);
            expect($uuids)->toContain($studentA->uuid);
            expect(count($uuids))->toBe(1);
        });
    });

    // -------------------------------------------------------------------------
    // School filter
    // -------------------------------------------------------------------------
    describe('school filter', function () {
        it('filters students by school when schoolId is provided', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantA)->create();

            $studentInA = studentRepoCreateStudent($this->tenantA, $schoolA);
            $studentInB = studentRepoCreateStudent($this->tenantA, $schoolB);

            $result = $this->repo->findAllPaginated(
                new StudentListCriteria(schoolId: $schoolA->id, isOwner: true)
            );

            $uuids = array_map(fn (Student $s) => $s->getUserUuid(), $result['items']);
            expect($uuids)->toContain($studentInA->uuid);
            expect($uuids)->not->toContain($studentInB->uuid);
        });

        it('returns all tenant students when no school filter is applied', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantA)->create();

            $studentInA = studentRepoCreateStudent($this->tenantA, $schoolA);
            $studentInB = studentRepoCreateStudent($this->tenantA, $schoolB);

            $result = $this->repo->findAllPaginated(new StudentListCriteria(isOwner: true));

            $uuids = array_map(fn (Student $s) => $s->getUserUuid(), $result['items']);
            expect($uuids)->toContain($studentInA->uuid);
            expect($uuids)->toContain($studentInB->uuid);
        });
    });

    // -------------------------------------------------------------------------
    // findByUserUuid
    // -------------------------------------------------------------------------
    describe('findByUserUuid', function () {
        it('finds a student by user uuid within the current tenant', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = studentRepoCreateStudent($this->tenantA, $school);

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

            // Create student in tenant B
            $userInB = User::factory()->create([
                'tenant_id' => $this->tenantB->id,
                'is_staff' => false,
            ]);
            $studentRole = RoleModel::firstOrCreate(
                ['slug' => 'student', 'tenant_id' => null],
                ['name' => 'Student', 'hierarchy_level' => 9, 'is_system_role' => false],
            );
            UserRoleAssignment::create([
                'user_id' => $userInB->id,
                'role_id' => $studentRole->id,
                'school_id' => $schoolB->id,
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);
            StudentProfile::factory()->forUser($userInB)->create();

            // Repo is scoped to tenant A — should not find tenant B's student
            $result = $this->repo->findByUserUuid($userInB->uuid);

            expect($result)->toBeNull();
        });

        it('returns a Student entity (not an Eloquent model)', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = studentRepoCreateStudent($this->tenantA, $school);

            $result = $this->repo->findByUserUuid($user->uuid);

            expect($result)->toBeInstanceOf(Student::class);
        });
    });

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------
    describe('create', function () {
        it('creates a student profile linked to the user', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $user = User::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'is_staff' => false,
            ]);

            $student = $this->repo->create(
                userUuid: $user->uuid,
                birthDate: null,
                nationalId: null,
                enrollmentNumber: 'ENR-999',
                gender: null,
                bloodType: null,
                groupId: null,
            );

            expect($student)->toBeInstanceOf(Student::class);

            $this->assertDatabaseHas('student_profiles', [
                'user_id' => $user->id,
                'enrollment_number' => 'ENR-999',
            ]);
        });
    });

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------
    describe('pagination', function () {
        it('returns the correct pagination envelope shape', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            studentRepoCreateStudent($this->tenantA, $school);

            $result = $this->repo->findAllPaginated(new StudentListCriteria(isOwner: true, perPage: 20, page: 1));

            expect($result)->toHaveKeys(['items', 'total', 'per_page', 'current_page', 'last_page']);
            expect($result['current_page'])->toBe(1);
            expect($result['per_page'])->toBe(20);
        });

        it('respects per_page and page parameters', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();

            for ($i = 1; $i <= 5; $i++) {
                studentRepoCreateStudent($this->tenantA, $school);
            }

            $page1 = $this->repo->findAllPaginated(new StudentListCriteria(isOwner: true, perPage: 2, page: 1));
            $page2 = $this->repo->findAllPaginated(new StudentListCriteria(isOwner: true, perPage: 2, page: 2));

            expect(count($page1['items']))->toBe(2);
            expect($page1['current_page'])->toBe(1);
            expect($page2['current_page'])->toBe(2);
        });
    });
});
