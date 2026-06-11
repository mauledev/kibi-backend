<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;

/**
 * Tests for the redesigned AssignRoleToUserUseCase using actor slug-based authority
 * and mutual role exclusion enforcement.
 */
describe('AssignRoleToUserUseCase — redesigned actor rules and exclusions', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->schoolRepo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new AssignRoleToUserUseCase(
            $this->userRepo,
            $this->roleRepo,
            $this->assignmentRepo,
            $this->schoolRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function v2User(int $id, string $uuid): User
    {
        return new User(
            id: $id,
            uuid: $uuid,
            isStaff: false,
            email: "{$uuid}@test.com",
            firstName: 'Test',
            lastNamePaternal: 'User',
            lastNameMaternal: null,
            passwordHash: 'hash',
        );
    }

    function v2Role(int $id, string $slug, int $level = 5, bool $deleted = false): Role
    {
        return new Role(
            id: $id,
            uuid: "{$slug}-uuid",
            tenantId: 1,
            categoryId: null,
            name: ucfirst($slug),
            slug: $slug,
            hierarchyLevel: $level,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $deleted ? new DateTimeImmutable('2025-01-01') : null,
        );
    }

    function v2Assignment(int $id = 99, ?int $schoolId = null): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $id,
            uuid: 'v2-assignment-uuid',
            userId: 20,
            roleId: 10,
            schoolId: $schoolId,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
    }

    function v2MockUsers(object $test): void
    {
        $test->userRepo->shouldReceive('findByUuid')
            ->with('actor-uuid')
            ->andReturn(v2User(1, 'actor-uuid'));
        $test->userRepo->shouldReceive('findByUuid')
            ->with('target-uuid')
            ->andReturn(v2User(20, 'target-uuid'));
    }

    it('throws HierarchyViolationException when actor is not owner, gestor, or director', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'teacher',
            targetUserUuid: 'target-uuid',
            roleUuid: 'some-role-uuid',
            schoolUuid: 'school-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws OwnerRoleAssignmentException when trying to assign the owner role', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'owner-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('owner-uuid')
            ->andReturn(v2Role(2, 'owner', 2));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(OwnerRoleAssignmentException::class);
    });

    it('throws HierarchyViolationException when director tries to assign school_manager role', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'director',
            targetUserUuid: 'target-uuid',
            roleUuid: 'gestor-uuid',
            schoolUuid: 'school-uuid',
        );

        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-uuid')->andReturn(5);
        $this->roleRepo->shouldReceive('findByUuid')
            ->with('gestor-uuid')
            ->andReturn(v2Role(3, 'school_manager', 3));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws RoleExclusionException when assigning teacher to a user who already has student in the same school', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'teacher-uuid',
            schoolUuid: 'school-uuid',
        );

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('teacher-uuid')
            ->andReturn(v2Role(10, 'teacher', 5));

        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-uuid')->andReturn(5);

        // User already has 'student' in school 5
        $this->assignmentRepo->shouldReceive('findActiveRoleSlugsForUserInSchool')
            ->with(20, 5)
            ->andReturn(['student']);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleExclusionException::class);
    });

    it('throws RoleExclusionException when assigning tutor to a user who already has teacher in the same school', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'school_manager',
            targetUserUuid: 'target-uuid',
            roleUuid: 'tutor-uuid',
            schoolUuid: 'school-uuid',
        );

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('tutor-uuid')
            ->andReturn(v2Role(12, 'tutor', 6));

        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-uuid')->andReturn(5);

        // User already has teacher in school 5
        $this->assignmentRepo->shouldReceive('findActiveRoleSlugsForUserInSchool')
            ->with(20, 5)
            ->andReturn(['teacher']);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleExclusionException::class);
    });

    it('succeeds when the same user has teacher in school A and tutor in school B (different schools)', function () {
        v2MockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'tutor-uuid',
            schoolUuid: 'school-b-uuid',
        );

        $tutorRole = v2Role(12, 'tutor', 6);

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('tutor-uuid')
            ->andReturn($tutorRole);

        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-b-uuid')->andReturn(2);

        // No conflicting role in school B
        $this->assignmentRepo->shouldReceive('findActiveRoleSlugsForUserInSchool')
            ->with(20, 2)
            ->andReturn(['finance']); // no teacher/student in school B

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->with(20, 12, 2)
            ->andReturn(null);

        $this->assignmentRepo->shouldReceive('create')
            ->once()
            ->with(20, 12, 2, 1)
            ->andReturn(v2Assignment(99, 2));

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
    });
});
