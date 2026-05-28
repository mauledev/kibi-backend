<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\RevokePermissionFromRole\RevokePermissionFromRoleInput;
use App\Modules\Roles\Application\UseCases\RevokePermissionFromRole\RevokePermissionFromRoleUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Auth\Access\AuthorizationException;

describe('RevokePermissionFromRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new RevokePermissionFromRoleUseCase(
            $this->roleRepo,
            $this->permissionRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function revokeRoleEntity(array $overrides = []): Role
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

    function revokePermissionEntity(string $slug = 'grade.publish'): Permission
    {
        return new Permission(
            id: 20,
            uuid: 'perm-uuid',
            categoryId: 1,
            name: 'Publish Grade',
            slug: $slug,
        );
    }

    it('throws AuthorizationException when actor does not hold manage.permissions', function () {
        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: false,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AuthorizationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            roleUuid: 'nonexistent',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $role = revokeRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when target role is a system role', function () {
        $role = revokeRoleEntity(['isSystemRole' => true]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws HierarchyViolationException when role has same level as actor', function () {
        $role = revokeRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when role has lower level than actor', function () {
        $role = revokeRoleEntity(['hierarchyLevel' => 3]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws PermissionNotFoundException when permission does not exist', function () {
        $role = revokeRoleEntity(['hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->permissionRepo->shouldReceive('findByUuid')->once()->with('nonexistent-perm')->andReturn(null);

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'nonexistent-perm',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(PermissionNotFoundException::class);
    });

    it('detaches permission and writes audit log when all checks pass', function () {
        $role = revokeRoleEntity(['hierarchyLevel' => 5]);
        $permission = revokePermissionEntity('grade.publish');

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);
        $this->permissionRepo->shouldReceive('findByUuid')->once()->andReturn($permission);
        $this->roleRepo->shouldReceive('detachPermission')->once()->with(10, 20);
        $this->audit->shouldReceive('log')->once();

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->useCase->execute($input);
    });

    it('never calls detachPermission when authorization fails', function () {
        $this->roleRepo->shouldNotReceive('detachPermission');
        $this->audit->shouldNotReceive('log');

        $input = new RevokePermissionFromRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: false,
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AuthorizationException::class);
    });
});
