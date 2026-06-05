<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment\RestorePermissionToAssignmentInput;
use App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment\RestorePermissionToAssignmentUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;

describe('RestorePermissionToAssignmentUseCase', function () {
    beforeEach(function () {
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new RestorePermissionToAssignmentUseCase(
            $this->assignmentRepo,
            $this->permissionRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeRestoreAssignment(int $id = 99): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $id,
            uuid: 'assignment-uuid',
            userId: 20,
            roleId: 10,
            schoolId: 5,
            assignedBy: 1,
            assignedAt: new DateTimeImmutable,
        );
    }

    function makeRestorePermission(int $id = 1): Permission
    {
        return new Permission(
            id: $id,
            uuid: 'perm-uuid',
            categoryId: 1,
            name: 'Test Permission',
            slug: 'grade.view',
        );
    }

    it('throws AssignmentNotFoundException when assignment does not exist', function () {
        $input = new RestorePermissionToAssignmentInput(
            assignmentUuid: 'nonexistent-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AssignmentNotFoundException::class);
    });

    it('throws PermissionNotFoundException when permission does not exist', function () {
        $input = new RestorePermissionToAssignmentInput(
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'bad-perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeRestoreAssignment());

        $this->permissionRepo->shouldReceive('findByUuid')
            ->with('bad-perm-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(PermissionNotFoundException::class);
    });

    it('removes the denial row and logs audit when denial exists', function () {
        $input = new RestorePermissionToAssignmentInput(
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeRestoreAssignment());

        $this->permissionRepo->shouldReceive('findByUuid')
            ->with('perm-uuid')
            ->andReturn(makeRestorePermission());

        $this->assignmentRepo->shouldReceive('removeDenial')
            ->once()
            ->with(99, 1);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    it('is idempotent — calling when denial does not exist does not throw', function () {
        $input = new RestorePermissionToAssignmentInput(
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeRestoreAssignment());

        $this->permissionRepo->shouldReceive('findByUuid')
            ->with('perm-uuid')
            ->andReturn(makeRestorePermission());

        $this->assignmentRepo->shouldReceive('removeDenial')
            ->once()
            ->with(99, 1);

        $this->audit->shouldReceive('log')->once();

        // Should not throw
        $this->useCase->execute($input);
    });
});
