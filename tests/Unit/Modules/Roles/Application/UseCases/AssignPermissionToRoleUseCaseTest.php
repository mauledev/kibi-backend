<?php

declare(strict_types=1);

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleInput;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Auth\Access\AuthorizationException;

describe('AssignPermissionToRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->useCase = new AssignPermissionToRoleUseCase(
            $this->roleRepo,
            $this->permissionRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeRoleEntity(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 10,
            publicId: $overrides['publicId'] ?? 'role-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 4,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: $overrides['permissions'] ?? [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    function makePermissionEntity(string $slug = 'grade.publish'): Permission
    {
        return new Permission(
            id: 20,
            publicId: 'perm-uuid',
            categoryId: 1,
            name: 'Publish Grade',
            slug: $slug,
        );
    }

    it('throws AuthorizationException when actor does not hold manage.permissions', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: false,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(AuthorizationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'nonexistent-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $role = makeRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when target role is a system role', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $role = makeRoleEntity(['isSystemRole' => true]);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws HierarchyViolationException when target role has same hierarchy level as actor', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4, // same as role
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $role = makeRoleEntity(['hierarchyLevel' => 4]);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when target role has lower hierarchy level than actor', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $role = makeRoleEntity(['hierarchyLevel' => 3]); // more privileged

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws PermissionNotFoundException when permission does not exist', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'nonexistent-perm-uuid',
        );

        $role = makeRoleEntity(['hierarchyLevel' => 5]);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByPublicId')
            ->once()
            ->with('nonexistent-perm-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(PermissionNotFoundException::class);
    });

    it('attaches permission and writes audit log when all checks pass', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        $role = makeRoleEntity(['hierarchyLevel' => 5]);
        $permission = makePermissionEntity('grade.publish');

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($permission);

        $this->roleRepo->shouldReceive('attachPermission')
            ->once()
            ->with($role->getId(), $permission->getId());

        $this->audit->shouldReceive('log')
            ->once()
            ->with('permission.grant', 1, $role->getId(), null, null, Mockery::type('array'));

        $this->useCase->execute($input);
    });

    it('is idempotent and does not call attachPermission when permission already present', function () {
        $permission = makePermissionEntity('grade.publish');

        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            actorCanManagePermissions: true,
            rolePublicId: 'role-uuid',
            permissionPublicId: 'perm-uuid',
        );

        // Role already has the permission in its collection
        $role = makeRoleEntity([
            'hierarchyLevel' => 5,
            'permissions' => [$permission],
        ]);

        $this->roleRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByPublicId')
            ->once()
            ->andReturn($permission);

        $this->roleRepo->shouldNotReceive('attachPermission');
        $this->audit->shouldNotReceive('log');

        $this->useCase->execute($input);
    });
});
