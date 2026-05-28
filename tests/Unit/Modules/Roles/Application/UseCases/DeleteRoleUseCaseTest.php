<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\DeleteRole\DeleteRoleInput;
use App\Modules\Roles\Application\UseCases\DeleteRole\DeleteRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

describe('DeleteRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new DeleteRoleUseCase($this->roleRepo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function deleteRoleEntity(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 10,
            uuid: $overrides['uuid'] ?? 'role-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            name: $overrides['name'] ?? 'Coordinador',
            slug: $overrides['slug'] ?? 'coordinador',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 5,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'nonexistent');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is already soft-deleted', function () {
        $role = deleteRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when trying to delete a system role', function () {
        $role = deleteRoleEntity(['isSystemRole' => true]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws HierarchyViolationException when role has the same hierarchy level as actor', function () {
        $role = deleteRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when role has a lower hierarchy level than actor', function () {
        // Lower number = more privileged
        $role = deleteRoleEntity(['hierarchyLevel' => 3]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('deletes the role and writes audit log when all checks pass', function () {
        $role = deleteRoleEntity(['hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldReceive('delete')->once()->with('role-uuid');
        $this->audit->shouldReceive('log')->once();

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid');

        $this->useCase->execute($input);
    });

    it('never calls delete when hierarchy check fails', function () {
        $role = deleteRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldNotReceive('delete');
        $this->audit->shouldNotReceive('log');

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('never calls delete when system role check fails', function () {
        $role = deleteRoleEntity(['isSystemRole' => true, 'hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldNotReceive('delete');
        $this->audit->shouldNotReceive('log');

        $input = new DeleteRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });
});
