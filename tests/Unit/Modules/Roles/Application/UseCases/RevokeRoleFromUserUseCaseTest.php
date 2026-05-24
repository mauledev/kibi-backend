<?php

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserInput;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('RevokeRoleFromUserUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->useCase = new RevokeRoleFromUserUseCase(
            $this->roleRepo,
            $this->assignmentRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function revokeRole(int $level = 5, bool $deleted = false): Role
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

    function revokeAssignment(int $id = 50): UserRoleAssignment
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
        $input = new RevokeRoleFromUserInput(
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
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(revokeRole(deleted: true));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws HierarchyViolationException when actor has equal hierarchy level to target role', function () {
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(revokeRole(level: 4));

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when actor has higher privilege than target role', function () {
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(revokeRole(level: 3)); // lower number = more privileged

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws AssignmentNotFoundException when no active assignment exists', function () {
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn(revokeRole(level: 5));

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->with(20, 10, null)
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AssignmentNotFoundException::class);
    });

    it('revokes assignment and writes audit log when all checks pass', function () {
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: null,
        );

        $role = revokeRole(level: 5);
        $activeAssignment = revokeAssignment(50);
        $revokedAssignment = new UserRoleAssignment(
            id: 50,
            userId: 20,
            roleId: 10,
            schoolId: null,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
            revokedAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->with(20, 10, null)
            ->andReturn($activeAssignment);

        $this->assignmentRepo->shouldReceive('revoke')
            ->once()
            ->with(50)
            ->andReturn($revokedAssignment);

        $this->audit->shouldReceive('log')
            ->once()
            ->with('role.revoke', 1, 50, null, Mockery::type('array'));

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(UserRoleAssignment::class);
        expect($result->isActive())->toBeFalse();
    });

    it('passes schoolId when looking up active assignment', function () {
        $input = new RevokeRoleFromUserInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            targetUserId: 20,
            rolePublicId: 'role-public-uuid',
            schoolId: 7,
        );

        $role = revokeRole(level: 5);
        $activeAssignment = new UserRoleAssignment(
            id: 50,
            userId: 20,
            roleId: 10,
            schoolId: 7,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
        $revokedAssignment = new UserRoleAssignment(
            id: 50,
            userId: 20,
            roleId: 10,
            schoolId: 7,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
            revokedAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->assignmentRepo->shouldReceive('findActiveByUserAndRole')
            ->once()
            ->with(20, 10, 7)
            ->andReturn($activeAssignment);

        $this->assignmentRepo->shouldReceive('revoke')
            ->once()
            ->andReturn($revokedAssignment);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
