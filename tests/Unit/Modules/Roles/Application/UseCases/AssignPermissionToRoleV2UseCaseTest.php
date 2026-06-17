<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleInput;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

/**
 * Tests for scope/category enforcement in the roles redesign.
 * Owner, school_manager, and superadmin are protected roles.
 * Custom roles (no category, not reserved slug) accept permissions from any scope.
 */
describe('AssignPermissionToRoleUseCase — scope and system-role guards (redesign)', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new AssignPermissionToRoleUseCase(
            $this->roleRepo,
            $this->permissionRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function v2PermRole(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 10,
            uuid: $overrides['uuid'] ?? 'role-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            categoryId: array_key_exists('categoryId', $overrides) ? $overrides['categoryId'] : 5,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 5,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: $overrides['permissions'] ?? [],
            createdAt: new DateTimeImmutable,
            deletedAt: null,
        );
    }

    function v2Perm(string $slug = 'grade.publish', int $categoryId = 5): Permission
    {
        return new Permission(
            id: 20,
            uuid: 'perm-uuid',
            categoryId: $categoryId,
            name: ucfirst(str_replace('.', ' ', $slug)),
            slug: $slug,
        );
    }

    // --- System role guard ---

    it('throws SystemRoleViolationException when trying to manage permissions on owner role', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'school_manager',
            roleUuid: 'owner-uuid',
            permissionUuid: 'perm-uuid',
        );

        $ownerRole = v2PermRole(['slug' => 'owner', 'categoryId' => null]);

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('owner-uuid')
            ->andReturn($ownerRole);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    it('throws SystemRoleViolationException when trying to manage permissions on school_manager role', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'gestor-uuid',
            permissionUuid: 'perm-uuid',
        );

        $gestorRole = v2PermRole(['slug' => 'school_manager', 'categoryId' => null]);

        $this->roleRepo->shouldReceive('findByUuid')
            ->with('gestor-uuid')
            ->andReturn($gestorRole);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(SystemRoleViolationException::class);
    });

    // --- Category mismatch guard ---

    it('throws HierarchyViolationException when permission category differs from role category and is not common', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        // School-scoped role (categoryId = 5 → 'school/director')
        $role = v2PermRole(['categoryId' => 5, 'slug' => 'director']);

        $this->roleRepo->shouldReceive('findByUuid')->with('role-uuid')->andReturn($role);

        // Permission from a different category that is not 'common' (categoryId = 99 → 'school/finance')
        $permission = v2Perm('payment.approve', 99);

        $this->permissionRepo->shouldReceive('findByUuid')->with('perm-uuid')->andReturn($permission);
        $this->permissionRepo->shouldReceive('findCategoryName')->with(99)->andReturn('finance');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when permission is in common category but different scope', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        // School-scoped role (categoryId = 5 → 'school/director')
        $role = v2PermRole(['categoryId' => 5, 'slug' => 'director']);

        $this->roleRepo->shouldReceive('findByUuid')->with('role-uuid')->andReturn($role);

        // Permission in 'tenant/common' (categoryId = 88) — same name but different scope
        $permission = v2Perm('user.view', 88);

        $this->permissionRepo->shouldReceive('findByUuid')->with('perm-uuid')->andReturn($permission);
        $this->permissionRepo->shouldReceive('findCategoryName')->with(88)->andReturn('common');
        $this->permissionRepo->shouldReceive('findCategoryScope')->with(5)->andReturn('school');
        $this->permissionRepo->shouldReceive('findCategoryScope')->with(88)->andReturn('tenant');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('succeeds when permission is in the common category of the same scope', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        // School-scoped role (categoryId = 5 → 'school/director')
        $role = v2PermRole(['categoryId' => 5, 'slug' => 'director']);

        // Permission in 'school/common' (categoryId = 77)
        $permission = v2Perm('user.view', 77);

        $this->roleRepo->shouldReceive('findByUuid')->with('role-uuid')->andReturn($role);
        $this->permissionRepo->shouldReceive('findByUuid')->with('perm-uuid')->andReturn($permission);
        $this->permissionRepo->shouldReceive('findCategoryName')->with(77)->andReturn('common');
        $this->permissionRepo->shouldReceive('findCategoryScope')->with(5)->andReturn('school');
        $this->permissionRepo->shouldReceive('findCategoryScope')->with(77)->andReturn('school');

        $this->roleRepo->shouldReceive('attachPermission')->once()->with($role->getId(), $permission->getId());
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    // --- Same category (exact match) ---

    it('succeeds when permission and role share the same category', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'role-uuid',
            permissionUuid: 'perm-uuid',
        );

        $role = v2PermRole(['categoryId' => 5, 'slug' => 'director']);
        $permission = v2Perm('grade.publish', 5);

        $this->roleRepo->shouldReceive('findByUuid')->with('role-uuid')->andReturn($role);
        $this->permissionRepo->shouldReceive('findByUuid')->with('perm-uuid')->andReturn($permission);

        // Same categoryId → no findCategoryName or findCategoryScope calls needed.
        $this->roleRepo->shouldReceive('attachPermission')
            ->once()
            ->with($role->getId(), $permission->getId());

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });

    // --- Custom role has no scope restriction ---

    it('custom roles with no category accept permissions from any scope', function () {
        $input = new AssignPermissionToRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            roleUuid: 'custom-uuid',
            permissionUuid: 'staff-perm-uuid',
        );

        // Custom role: categoryId = null, slug not reserved
        $customRole = v2PermRole(['categoryId' => null, 'slug' => 'my_custom_role', 'uuid' => 'custom-uuid', 'tenantId' => 1]);

        $this->roleRepo->shouldReceive('findByUuid')->with('custom-uuid')->andReturn($customRole);

        // Staff-scoped permission
        $staffPerm = v2Perm('support.ticket.close', 99);
        $this->permissionRepo->shouldReceive('findByUuid')->with('staff-perm-uuid')->andReturn($staffPerm);

        // Custom role skips scope check — no findCategoryScope calls needed
        $this->roleRepo->shouldReceive('attachPermission')
            ->once()
            ->with($customRole->getId(), $staffPerm->getId());

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
