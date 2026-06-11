<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleInput;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

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
            categoryId: null,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 4,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('throws HierarchyViolationException when actor slug is not an authorised actor', function () {
        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'prefect', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn(null);

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'nonexistent', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $role = updateRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when trying to rename a system role', function () {
        $role = updateRoleEntity(['isSystemRole' => true, 'slug' => 'superadmin']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws HierarchyViolationException when director tries to update school_manager role', function () {
        $role = updateRoleEntity(['slug' => 'school_manager', 'categoryId' => null]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'director', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when director tries to update owner role', function () {
        $role = updateRoleEntity(['slug' => 'owner']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'director', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('renames the role, persists it, and writes audit log when owner updates any role', function () {
        $originalRole = updateRoleEntity(['name' => 'Old Name', 'slug' => 'finance']);
        $updatedRole = updateRoleEntity(['name' => 'New Name', 'slug' => 'finance']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($originalRole);
        $this->roleRepo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (Role $r) => $r->getName() === 'New Name'))
            ->andReturn($updatedRole);
        $this->audit->shouldReceive('log')
            ->once()
            ->with('role.update', 1, 10, null, Mockery::type('array'), Mockery::type('array'));

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid', name: 'New Name');
        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(Role::class);
        expect($result->getName())->toBe('New Name');
    });

    it('renames the role when school_manager updates any non-system role', function () {
        $originalRole = updateRoleEntity(['name' => 'Old', 'slug' => 'director']);
        $updatedRole = updateRoleEntity(['name' => 'Updated Director', 'slug' => 'director']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($originalRole);
        $this->roleRepo->shouldReceive('update')
            ->once()
            ->andReturn($updatedRole);
        $this->audit->shouldReceive('log')->once();

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'school_manager', uuid: 'role-uuid', name: 'Updated Director');
        $result = $this->useCase->execute($input);

        expect($result->getName())->toBe('Updated Director');
    });

    it('never calls update when actor is not authorised', function () {
        $this->roleRepo->shouldNotReceive('findByUuid');
        $this->roleRepo->shouldNotReceive('update');
        $this->audit->shouldNotReceive('log');

        $input = new UpdateRoleInput(actorUserId: 1, actorSlug: 'student', uuid: 'role-uuid', name: 'New');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });
});
