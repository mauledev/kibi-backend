<?php

use App\Common\Audit\AuditLogger;
use App\Common\Audit\Events\RoleAuditEvent;
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
            uuid: $overrides['uuid'] ?? 'role-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            categoryId: $overrides['categoryId'] ?? null,
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
            uuid: 'perm-uuid',
            categoryId: 1,
            name: 'Publish Grade',
            slug: $slug,
        );
    }

    it('throws HierarchyViolationException when actor slug is unknown', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'prefect',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws RoleNotFoundException when role does not exist', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'nonexistent-uuid',
            permissionUuid: 'perm-uuid',
        );

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws SystemRoleViolationException when target role is superadmin', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        $role = makeRoleEntity(['slug' => 'superadmin', 'isSystemRole' => true]);

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws SystemRoleViolationException when director tries to manage school_manager role', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'director',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        // school_manager is in PROTECTED_SLUGS — SystemRoleViolationException fires first
        $role = makeRoleEntity(['slug' => 'school_manager', 'categoryId' => null]);

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($role);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws PermissionNotFoundException when permission does not exist', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'nonexistent-perm-uuid',
        );

        $role = makeRoleEntity();

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByUuid')
            ->once()
            ->with('nonexistent-perm-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(PermissionNotFoundException::class);
    });

    it('attaches permission and writes audit log when all checks pass', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        $role = makeRoleEntity();
        $permission = makePermissionEntity('grade.publish');

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($permission);

        $this->permissionRepo->shouldReceive('findCategoryScope')
            ->andReturn('school');

        $this->roleRepo->shouldReceive('attachPermission')
            ->once()
            ->with($role->getId(), $permission->getId());

        $this->audit->shouldReceive('log')
            ->once()
            ->with(RoleAuditEvent::PERMISSION_GRANT, 1, $role->getId(), null, null, Mockery::type('array'));

        $this->useCase->execute($input);
    });

    it('is idempotent and does not call attachPermission when permission already present', function () {
        $permission = makePermissionEntity('grade.publish');

        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        // Role already has the permission in its collection
        $role = makeRoleEntity([
            'permissions' => [$permission],
        ]);

        $this->roleRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($role);

        $this->permissionRepo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($permission);

        $this->permissionRepo->shouldReceive('findCategoryScope')
            ->andReturn('school');

        $this->roleRepo->shouldNotReceive('attachPermission');
        $this->audit->shouldNotReceive('log');

        $this->useCase->execute($input);
    });
});
