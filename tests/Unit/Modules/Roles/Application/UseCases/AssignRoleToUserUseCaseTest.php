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

    function buildUser(int $id, string $uuid): User
    {
        return new User(
            id: $id,
            uuid: $uuid,
            tenantId: 1,
            email: "{$uuid}@test.com",
            fullName: 'Test User',
            passwordHash: 'hash',
        );
    }

    function buildRole(int $level = 5, bool $deleted = false): Role
    {
        return new Role(
            id: 10,
            uuid: 'role-public-uuid',
            tenantId: 1,
            name: 'Coordinador',
            slug: 'coordinador',
            hierarchyLevel: $level,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $deleted ? new DateTimeImmutable('2025-01-01') : null,
        );
    }

    function buildAssignment(int $id = 99): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $id,
            userId: 20,
            roleId: 10,
            schoolId: null,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
    }

    function mockUsers(object $test, int $actorId = 1, int $targetId = 20): void
    {
        $test->userRepo->shouldReceive('findByUuid')
            ->with('actor-uuid')
            ->andReturn(buildUser($actorId, 'actor-uuid'));
        $test->userRepo->shouldReceive('findByUuid')
            ->with('target-uuid')
            ->andReturn(buildUser($targetId, 'target-uuid'));
    }

    it('throws RoleNotFoundException when role does not exist', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'nonexistent',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        expect(fn () => $this->useCase->execute($input))->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(deleted: true));

        expect(fn () => $this->useCase->execute($input))->toThrow(RoleNotFoundException::class);
    });

    it('throws HierarchyViolationException when actor has equal hierarchy level to target role', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 4,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(level: 4));

        expect(fn () => $this->useCase->execute($input))->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when actor has higher privilege level than target role', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 4,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(level: 3));

        expect(fn () => $this->useCase->execute($input))->toThrow(HierarchyViolationException::class);
    });

    it('creates assignment and writes audit log when checks pass', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $role = buildRole(level: 5);
        $assignment = buildAssignment(99);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, null)->andReturn(null);
        $this->assignmentRepo->shouldReceive('create')->once()->with(20, 10, null, 1)->andReturn($assignment);
        $this->audit->shouldReceive('log')->once()->with('role.assign', 1, 99, null, null, Mockery::type('array'));

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
        expect($result->getId())->toBe(99);
    });

    it('returns existing assignment without creating a new one when active assignment exists', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(level: 5));
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->andReturn(buildAssignment(77));
        $this->assignmentRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        $result = $this->useCase->execute($input);

        expect($result->getId())->toBe(77);
    });

    it('level 1 actor can assign any role regardless of level', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 1,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(level: 2));
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->andReturn(null);
        $this->assignmentRepo->shouldReceive('create')->once()->andReturn(buildAssignment(55));
        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
    });

    it('resolves schoolUuid to schoolId and passes it through to assignment creation', function () {
        mockUsers($this);

        $input = new AssignRoleToUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: 'school-uuid',
        );

        $this->schoolRepo->shouldReceive('findIdByUuid')->once()->with('school-uuid')->andReturn(5);
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(buildRole(level: 5));
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, 5)->andReturn(null);
        $this->assignmentRepo->shouldReceive('create')->once()->with(20, 10, 5, 1)->andReturn(buildAssignment());
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
