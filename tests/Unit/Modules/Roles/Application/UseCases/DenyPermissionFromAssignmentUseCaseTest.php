<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment\DenyPermissionFromAssignmentInput;
use App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment\DenyPermissionFromAssignmentUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

describe('DenyPermissionFromAssignmentUseCase', function () {
    beforeEach(function () {
        $this->assignmentRepo = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new DenyPermissionFromAssignmentUseCase(
            $this->assignmentRepo,
            $this->permissionRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeDenyAssignment(int $id = 99): UserRoleAssignment
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

    function makeDenyPermission(int $id = 1): Permission
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
        $input = new DenyPermissionFromAssignmentInput(
            actorSlug: 'director',
            assignmentUuid: 'nonexistent-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AssignmentNotFoundException::class);
    });

    it('throws SystemRoleViolationException when assignment role is owner', function () {
        $input = new DenyPermissionFromAssignmentInput(
            actorSlug: 'director',
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeDenyAssignment());

        $this->assignmentRepo->shouldReceive('findRoleSlugByAssignmentId')
            ->with(99)
            ->andReturn('owner');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws SystemRoleViolationException when assignment role is gestor_escuelas', function () {
        $input = new DenyPermissionFromAssignmentInput(
            actorSlug: 'director',
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeDenyAssignment());

        $this->assignmentRepo->shouldReceive('findRoleSlugByAssignmentId')
            ->with(99)
            ->andReturn('gestor_escuelas');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws PermissionNotFoundException when permission does not exist', function () {
        $input = new DenyPermissionFromAssignmentInput(
            actorSlug: 'director',
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'bad-perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeDenyAssignment());

        $this->assignmentRepo->shouldReceive('findRoleSlugByAssignmentId')
            ->with(99)
            ->andReturn('director');

        $this->permissionRepo->shouldReceive('findByUuid')
            ->with('bad-perm-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(PermissionNotFoundException::class);
    });

    it('inserts a denial row when assignment and permission are valid', function () {
        $input = new DenyPermissionFromAssignmentInput(
            actorSlug: 'director',
            assignmentUuid: 'assignment-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->assignmentRepo->shouldReceive('findByUuid')
            ->with('assignment-uuid')
            ->andReturn(makeDenyAssignment());

        $this->assignmentRepo->shouldReceive('findRoleSlugByAssignmentId')
            ->with(99)
            ->andReturn('director');

        $this->permissionRepo->shouldReceive('findByUuid')
            ->with('perm-uuid')
            ->andReturn(makeDenyPermission());

        $this->assignmentRepo->shouldReceive('addDenial')
            ->once()
            ->with(99, 1)
            ->andReturn(true);

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
