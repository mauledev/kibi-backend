<?php

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('AssignRoleToUserUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->useCase = new AssignRoleToUserUseCase(
            $this->roleRepo,
            $this->assignmentRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function buildRole(int $level = 5, bool $deleted = false): Role
    {
        return new Role(
            id: 10,
            publicId: 'role-public-uuid',
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

    it('throws RoleNotFoundException when role does not exist', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'nonexistent',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(buildRole(deleted: true));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws HierarchyViolationException when actor has equal hierarchy level to target role', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 4, // same as role level
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(buildRole(level: 4));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when actor has higher privilege level than target role', function () {
        // Actor at level 4, role at level 3 (lower number = more privileged)
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(buildRole(level: 3));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('creates assignment and writes audit log when checks pass', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $role = buildRole(level: 5);
        $assignment = buildAssignment(99);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->with(20, 10, null)
            ->andReturn(null);

        $this->assignmentRepo->shouldReceive('create')
            ->once()
            ->with(20, 10, null, 1)
            ->andReturn($assignment);

        $this->audit->shouldReceive('log')
            ->once()
            ->with('role.assign', 1, 99, null, null, Mockery::type('array'));

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
        expect($result->getId())->toBe(99);
    });

    it('returns existing assignment without creating a new one when active assignment exists', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $role = buildRole(level: 5);
        $existing = buildAssignment(77);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->andReturn($existing);

        $this->assignmentRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        $result = $this->useCase->execute($input);

        expect($result->getId())->toBe(77);
    });

    it('level 1 actor can assign any role regardless of level', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 1,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $role = buildRole(level: 2);
        $assignment = buildAssignment(55);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->andReturn(null);

        $this->assignmentRepo->shouldReceive('create')
            ->once()
            ->andReturn($assignment);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
    });

    it('passes schoolId through to assignment creation', function () {
        $input = new AssignRoleToUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: 5,
        );

        $role = buildRole(level: 5);
        $assignment = buildAssignment();

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->with(20, 10, 5)
            ->andReturn(null);

        $this->assignmentRepo->shouldReceive('create')
            ->once()
            ->with(20, 10, 5, 1)
            ->andReturn($assignment);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
