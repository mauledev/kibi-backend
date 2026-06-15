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
            categoryId: $overrides['categoryId'] ?? null,
            name: $overrides['name'] ?? 'Coordinador',
            slug: $overrides['slug'] ?? 'coordinador',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 5,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('throws HierarchyViolationException when actor slug is not an authorised actor', function () {
        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'prefect', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'nonexistent');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is already soft-deleted', function () {
        $role = deleteRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when trying to delete a system role', function () {
        // Realistic: tenant system roles (director, teacher) always have a categoryId set.
        $role = deleteRoleEntity(['isSystemRole' => true, 'categoryId' => 1]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws SystemRoleViolationException when director tries to delete school_manager role', function () {
        // school_manager is not a custom role — blocked before any hierarchy check.
        $role = deleteRoleEntity(['slug' => 'school_manager']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'director', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws SystemRoleViolationException when director tries to delete owner role', function () {
        // owner is not a custom role — blocked before any hierarchy check.
        $role = deleteRoleEntity(['slug' => 'owner']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'director', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('deletes the role and writes audit log when owner deletes any custom role', function () {
        $role = deleteRoleEntity(['slug' => 'coordinador', 'hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldReceive('delete')->once()->with('role-uuid');
        $this->audit->shouldReceive('log')->once();

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid');

        $this->useCase->execute($input);
    });

    it('deletes the role when school_manager deletes a custom role', function () {
        $role = deleteRoleEntity(['slug' => 'coordinador']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldReceive('delete')->once()->with('role-uuid');
        $this->audit->shouldReceive('log')->once();

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'school_manager', uuid: 'role-uuid');

        $this->useCase->execute($input);
    });

    it('deletes the role when director deletes a non-protected role', function () {
        $role = deleteRoleEntity(['slug' => 'finance']);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldReceive('delete')->once()->with('role-uuid');
        $this->audit->shouldReceive('log')->once();

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'director', uuid: 'role-uuid');

        $this->useCase->execute($input);
    });

    it('never calls delete when system role check fails', function () {
        $role = deleteRoleEntity(['isSystemRole' => true, 'categoryId' => 1]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->roleRepo->shouldNotReceive('delete');
        $this->audit->shouldNotReceive('log');

        $input = new DeleteRoleInput(actorUserId: 1, actorSlug: 'owner', uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });
});
