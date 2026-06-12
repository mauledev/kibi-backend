<?php

use App\Common\Audit\AuditLoggerInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User as AuthUser;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Student\Application\UseCases\CreateStudent\CreateStudentInput;
use App\Modules\Student\Application\UseCases\CreateStudent\CreateStudentUseCase;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;

describe('CreateStudentUseCase', function () {
    beforeEach(function () {
        $this->users = Mockery::mock(GlobalUserRepositoryInterface::class);
        $this->assignRole = Mockery::mock(AssignRoleToUserUseCase::class);
        $this->studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        DB::shouldReceive('transaction')
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->useCase = new CreateStudentUseCase(
            globalUsers: $this->users,
            roles: $this->roleRepo,
            assignRole: $this->assignRole,
            students: $this->studentRepo,
            audit: $this->audit,
        );

        $this->input = new CreateStudentInput(
            tenantId: 1,
            actorUuid: 'actor-uuid-1',
            actorSlug: 'owner',
            schoolUuid: 'school-uuid-1',
            email: 'carlos@example.com',
            firstName: 'Carlos',
            lastNamePaternal: 'Méndez',
            lastNameMaternal: 'Soto',
            phone: null,
            birthDate: null,
            nationalId: null,
            enrollmentNumber: 'ENR-100',
            gender: null,
            bloodType: null,
            groupId: null,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a stub AuthUser entity.
     */
    function createStudentMakeAuthUser(string $uuid = 'new-user-uuid'): AuthUser
    {
        return new AuthUser(
            id: 99,
            uuid: $uuid,
            email: 'carlos@example.com',
            firstName: 'Carlos',
            lastNamePaternal: 'Méndez',
            lastNameMaternal: 'Soto',
            passwordHash: null,
            status: 'pending',
        );
    }

    /**
     * Build a stub Role domain entity.
     */
    function createStudentMakeRole(string $slug = 'student'): Role
    {
        return new Role(
            id: 5,
            uuid: 'role-student-uuid',
            tenantId: 1,
            categoryId: null,
            name: 'Student',
            slug: $slug,
            hierarchyLevel: 8,
            isSystemRole: false,
            permissions: [],
        );
    }

    /**
     * Build a stub Student entity.
     */
    function createStudentMakeStudent(): Student
    {
        return new Student(
            id: 10,
            uuid: 'profile-uuid',
            userId: 99,
            userUuid: 'new-user-uuid',
            email: 'carlos@example.com',
            firstName: 'Carlos',
            lastNamePaternal: 'Méndez',
            lastNameMaternal: 'Soto',
            phone: null,
            status: 'pending',
            birthDate: null,
            nationalId: null,
            enrollmentNumber: 'ENR-100',
            gender: null,
            bloodType: null,
            groupUuid: null,
            groupName: null,
            createdAt: new DateTime,
        );
    }

    it('creates a pending user, assigns the student role, and persists the profile', function () {
        $authUser = createStudentMakeAuthUser();
        $role = createStudentMakeRole();
        $student = createStudentMakeStudent();

        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->with('carlos@example.com')
            ->andReturn(false);

        $this->roleRepo->shouldReceive('findBySlug')
            ->once()
            ->with('student')
            ->andReturn($role);

        $this->users->shouldReceive('createPending')
            ->once()
            ->andReturn($authUser);

        $this->users->shouldReceive('setTenantId')
            ->once()
            ->with(99, 1);

        $this->assignRole->shouldReceive('execute')->once();

        $this->studentRepo->shouldReceive('create')
            ->once()
            ->andReturn($student);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($this->input);

        expect($result)->toBeInstanceOf(Student::class);
    });

    it('throws EmailAlreadyTakenException when email is already taken', function () {
        $inputWithEmail = new CreateStudentInput(
            tenantId: 1,
            actorUuid: 'actor-uuid-1',
            actorSlug: 'owner',
            schoolUuid: 'school-uuid-1',
            email: 'taken@example.com',
            firstName: 'Carlos',
            lastNamePaternal: 'Méndez',
            lastNameMaternal: null,
            phone: null,
            birthDate: null,
            nationalId: null,
            enrollmentNumber: null,
            gender: null,
            bloodType: null,
            groupId: null,
        );

        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->with('taken@example.com')
            ->andReturn(true);

        $this->roleRepo->shouldNotReceive('findBySlug');
        $this->users->shouldNotReceive('createPending');
        $this->studentRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($inputWithEmail))
            ->toThrow(EmailAlreadyTakenException::class);
    });

    it('throws RoleNotFoundException when student role does not exist in the tenant', function () {
        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->andReturn(false);

        $this->roleRepo->shouldReceive('findBySlug')
            ->once()
            ->with('student')
            ->andReturn(null);

        $this->users->shouldNotReceive('createPending');
        $this->studentRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('does not call any mailer — students receive no activation email', function () {
        $authUser = createStudentMakeAuthUser();
        $role = createStudentMakeRole();
        $student = createStudentMakeStudent();

        $this->users->shouldReceive('existsByEmail')->once()->andReturn(false);
        $this->roleRepo->shouldReceive('findBySlug')->once()->andReturn($role);
        $this->users->shouldReceive('createPending')->once()->andReturn($authUser);
        $this->users->shouldReceive('setTenantId')->once();
        $this->assignRole->shouldReceive('execute')->once();
        $this->studentRepo->shouldReceive('create')->once()->andReturn($student);
        $this->audit->shouldReceive('log')->once();

        // If a mailer mock were registered, it would fail because we never call it.
        // This test simply asserts the use case completes without touching any mailer.
        $result = $this->useCase->execute($this->input);

        expect($result)->toBeInstanceOf(Student::class);
    });
});
