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
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('AssignRoleToUserUseCase', function () {
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

    function assignBuildUser(int $id, string $uuid): User
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

    function assignBuildRole(string $slug = 'coordinador', bool $deleted = false): Role
    {
        return new Role(
            id: 10,
            uuid: 'role-public-uuid',
            tenantId: 1,
            categoryId: null,
            name: 'Coordinador',
            slug: $slug,
            hierarchyLevel: 5,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $deleted ? new DateTimeImmutable('2025-01-01') : null,
        );
    }

    function assignBuildAssignment(int $id = 99): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $id,
            uuid: 'test-assignment-uuid',
            userId: 20,
            roleId: 10,
            schoolId: null,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
    }

    function assignMockUsers(object $test, int $actorId = 1, int $targetId = 20): void
    {
        $test->userRepo->shouldReceive('findByUuid')
            ->with('actor-uuid')
            ->andReturn(assignBuildUser($actorId, 'actor-uuid'));
        $test->userRepo->shouldReceive('findByUuid')
            ->with('target-uuid')
            ->andReturn(assignBuildUser($targetId, 'target-uuid'));
    }

    it('throws HierarchyViolationException when actor slug is not owner/gestor/director', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'coordinador',
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        expect(fn () => $this->useCase->execute($input))->toThrow(HierarchyViolationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'nonexistent',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        expect(fn () => $this->useCase->execute($input))->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(assignBuildRole(deleted: true));

        expect(fn () => $this->useCase->execute($input))->toThrow(RoleNotFoundException::class);
    });

    it('throws OwnerRoleAssignmentException when trying to assign the owner role', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'owner-role-uuid',
            schoolUuid: null,
        );

        $ownerRole = new Role(
            id: 2,
            uuid: 'owner-role-uuid',
            tenantId: null,
            categoryId: null,
            name: 'Owner',
            slug: 'owner',
            hierarchyLevel: 2,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->with('owner-role-uuid')->andReturn($ownerRole);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(OwnerRoleAssignmentException::class);
    });

    it('throws HierarchyViolationException when director tries to assign gestor_escuelas', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'director',
            targetUserUuid: 'target-uuid',
            roleUuid: 'gestor-uuid',
            schoolUuid: null,
        );

        $gestorRole = new Role(
            id: 3,
            uuid: 'gestor-uuid',
            tenantId: null,
            categoryId: null,
            name: 'Gestor',
            slug: 'gestor_escuelas',
            hierarchyLevel: 3,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->with('gestor-uuid')->andReturn($gestorRole);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('creates assignment and writes audit log when checks pass', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'owner',
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $role = assignBuildRole();
        $assignment = assignBuildAssignment(99);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, null)->andReturn(null);
        $this->assignmentRepo->shouldReceive('create')->once()->with(20, 10, null, 1)->andReturn($assignment);
        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
        expect($result->getId())->toBe(99);
    });

    it('returns existing assignment without creating a new one when active assignment exists', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'gestor_escuelas',
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(assignBuildRole());
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->andReturn(assignBuildAssignment(77));
        $this->assignmentRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        $result = $this->useCase->execute($input);

        expect($result->getId())->toBe(77);
    });

    it('resolves schoolUuid to schoolId and passes it through to assignment creation', function () {
        assignMockUsers($this);

        // Use docente role to trigger the exclusion check (docente has exclusions)
        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'gestor_escuelas',
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: 'school-uuid',
        );

        $docenteRole = new Role(
            id: 10, uuid: 'role-public-uuid', tenantId: 1, categoryId: 1,
            name: 'Docente', slug: 'docente', hierarchyLevel: 7, isSystemRole: false,
            permissions: [], createdAt: new DateTimeImmutable,
        );

        $this->schoolRepo->shouldReceive('findIdByUuid')->once()->with('school-uuid')->andReturn(5);
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($docenteRole);
        $this->assignmentRepo->shouldReceive('findActiveRoleSlugsForUserInSchool')->once()->with(20, 5)->andReturn([]);
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, 5)->andReturn(null);
        $this->assignmentRepo->shouldReceive('create')->once()->with(20, 10, 5, 1)->andReturn(assignBuildAssignment());
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    it('throws RoleExclusionException when teacher is being assigned and student role exists', function () {
        assignMockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorSlug: 'director',
            targetUserUuid: 'target-uuid',
            roleUuid: 'docente-uuid',
            schoolUuid: 'school-uuid',
        );

        $docenteRole = new Role(
            id: 20,
            uuid: 'docente-uuid',
            tenantId: null,
            categoryId: 1,
            name: 'Docente',
            slug: 'docente',
            hierarchyLevel: 7,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: null,
        );

        $this->schoolRepo->shouldReceive('findIdByUuid')->once()->with('school-uuid')->andReturn(5);
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($docenteRole);
        $this->assignmentRepo->shouldReceive('findActiveRoleSlugsForUserInSchool')->once()->with(20, 5)->andReturn(['alumno']);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleExclusionException::class);
    });
});
