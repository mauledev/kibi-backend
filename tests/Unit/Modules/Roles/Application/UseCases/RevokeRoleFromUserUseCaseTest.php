<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserInput;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('RevokeRoleFromUserUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->schoolRepo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new RevokeRoleFromUserUseCase(
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

    function revokeUser(int $id, string $uuid): User
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

    function revokeRole(int $level = 5, bool $deleted = false): Role
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

    function revokeAssignment(int $id = 50, ?int $schoolId = null): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $id,
            userId: 20,
            roleId: 10,
            schoolId: $schoolId,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
    }

    function mockRevokeUsers(object $test, int $actorId = 1, int $targetId = 20): void
    {
        $test->userRepo->shouldReceive('findByUuid')
            ->with('actor-uuid')
            ->andReturn(revokeUser($actorId, 'actor-uuid'));
        $test->userRepo->shouldReceive('findByUuid')
            ->with('target-uuid')
            ->andReturn(revokeUser($targetId, 'target-uuid'));
    }

    it('throws RoleNotFoundException when role does not exist', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
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
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(revokeRole(deleted: true));

        expect(fn () => $this->useCase->execute($input))->toThrow(RoleNotFoundException::class);
    });

    it('throws HierarchyViolationException when actor has equal hierarchy level to target role', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 4,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(revokeRole(level: 4));

        expect(fn () => $this->useCase->execute($input))->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when actor has higher privilege than target role', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 4,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(revokeRole(level: 3));

        expect(fn () => $this->useCase->execute($input))->toThrow(HierarchyViolationException::class);
    });

    it('throws AssignmentNotFoundException when no active assignment exists', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(revokeRole(level: 5));
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, null)->andReturn(null);

        expect(fn () => $this->useCase->execute($input))->toThrow(AssignmentNotFoundException::class);
    });

    it('revokes assignment and writes audit log when all checks pass', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: null,
        );

        $role = revokeRole(level: 5);
        $active = revokeAssignment(50);
        $revoked = new UserRoleAssignment(
            id: 50, userId: 20, roleId: 10, schoolId: null,
            assignedBy: 1, assignedAt: new DateTimeImmutable, revokedAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, null)->andReturn($active);
        $this->assignmentRepo->shouldReceive('revoke')->once()->with(50)->andReturn($revoked);
        $this->audit->shouldReceive('log')->once()->with('role.revoke', 1, 50, null, Mockery::type('array'));

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
        expect($result->isActive())->toBeFalse();
    });

    it('resolves schoolUuid to schoolId when looking up active assignment', function () {
        mockRevokeUsers($this);

        $input = new RevokeRoleFromUserInput(
            actorUuid: 'actor-uuid',
            actorHierarchyLevel: 3,
            targetUserUuid: 'target-uuid',
            roleUuid: 'role-public-uuid',
            schoolUuid: 'school-uuid',
        );

        $active = revokeAssignment(50, 7);
        $revoked = new UserRoleAssignment(
            id: 50, userId: 20, roleId: 10, schoolId: 7,
            assignedBy: 1, assignedAt: new DateTimeImmutable, revokedAt: new DateTimeImmutable,
        );

        $this->schoolRepo->shouldReceive('findIdByUuid')->once()->with('school-uuid')->andReturn(7);
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(revokeRole(level: 5));
        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')->once()->with(20, 10, 7)->andReturn($active);
        $this->assignmentRepo->shouldReceive('revoke')->once()->andReturn($revoked);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
