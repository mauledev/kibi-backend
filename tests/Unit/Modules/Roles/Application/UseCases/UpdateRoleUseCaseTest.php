<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleInput;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('UpdateRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new UpdateRoleUseCase($this->roleRepo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function updateRoleEntity(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 10,
            uuid: $overrides['uuid'] ?? 'role-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 4,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(null);

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'nonexistent', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $role = updateRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws HierarchyViolationException when role has the same hierarchy level as actor', function () {
        $role = updateRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when role has a lower level than actor', function () {
        $role = updateRoleEntity(['hierarchyLevel' => 3]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('renames the role, persists it, and writes audit log when checks pass', function () {
        $originalRole = updateRoleEntity(['name' => 'Old Name', 'hierarchyLevel' => 5]);
        $updatedRole = updateRoleEntity(['name' => 'New Name', 'hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($originalRole);
        $this->roleRepo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (Role $r) => $r->getName() === 'New Name'))
            ->andReturn($updatedRole);
        $this->audit->shouldReceive('log')
            ->once()
            ->with('role.update', 1, 10, null, Mockery::type('array'), Mockery::type('array'));

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 3, uuid: 'role-uuid', name: 'New Name');
        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(Role::class);
        expect($result->getName())->toBe('New Name');
    });

    it('never calls update when hierarchy check fails', function () {
        $role = updateRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldNotReceive('update');
        $this->audit->shouldNotReceive('log');

        $input = new UpdateRoleInput(actorUserId: 1, actorHierarchyLevel: 4, uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });
});
