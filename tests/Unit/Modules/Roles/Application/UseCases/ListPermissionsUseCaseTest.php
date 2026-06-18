<?php

use App\Modules\Roles\Application\UseCases\ListPermissions\ListPermissionsUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('ListPermissionsUseCase', function () {
    beforeEach(function () {
        $this->permissionRepo = Mockery::mock(PermissionRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->useCase = new ListPermissionsUseCase($this->permissionRepo, $this->roleRepo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function listPermission(string $slug = 'grade.publish'): Permission
    {
        return new Permission(
            id: 1,
            uuid: 'perm-uuid',
            categoryId: 1,
            name: ucfirst(str_replace('.', ' ', $slug)),
            slug: $slug,
        );
    }

    it('returns all permissions from repository', function () {
        $permA = listPermission('grade.publish');
        $permB = listPermission('payment.approve');

        $this->permissionRepo->shouldReceive('findAll')->once()->andReturn([$permA, $permB]);

        $result = $this->useCase->execute();

        expect($result)->toHaveCount(2);
        expect($result[0]->getSlug())->toBe('grade.publish');
        expect($result[1]->getSlug())->toBe('payment.approve');
    });

    it('returns empty array when no permissions exist', function () {
        $this->permissionRepo->shouldReceive('findAll')->once()->andReturn([]);

        $result = $this->useCase->execute();

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('delegates entirely to the permission repository', function () {
        $this->permissionRepo->shouldReceive('findAll')->once()->andReturn([listPermission()]);

        $result = $this->useCase->execute();

        expect($result)->toHaveCount(1);
        expect($result[0])->toBeInstanceOf(Permission::class);
    });

    it('returns permissions from category and common category when roleUuid is given for a categorised role', function () {
        $role = new Role(
            id: 1,
            uuid: 'role-uuid',
            tenantId: 1,
            categoryId: 5,
            name: 'Director',
            slug: 'director',
            hierarchyLevel: 3,
            isSystemRole: false,
        );

        $this->roleRepo->shouldReceive('findByUuid')->with('role-uuid')->once()->andReturn($role);

        $permA = listPermission('grade.publish');
        $permB = listPermission('user.view');
        $this->permissionRepo->shouldReceive('findByCategoryIdOrCommon')->with(5)->once()->andReturn([$permA, $permB]);
        $this->permissionRepo->shouldNotReceive('findByCategoryId');
        $this->permissionRepo->shouldNotReceive('findAll');

        $result = $this->useCase->execute('role-uuid');

        expect($result)->toHaveCount(2);
        expect($result[0])->toBeInstanceOf(Permission::class);
        expect($result[1])->toBeInstanceOf(Permission::class);
    });

    it('returns all permissions when role has no category (custom role)', function () {
        $role = new Role(
            id: 2,
            uuid: 'custom-uuid',
            tenantId: 1,
            categoryId: null,
            name: 'Custom Role',
            slug: 'custom_role',
            hierarchyLevel: 5,
            isSystemRole: false,
        );

        $this->roleRepo->shouldReceive('findByUuid')->with('custom-uuid')->once()->andReturn($role);

        $this->permissionRepo->shouldReceive('findAll')->once()->andReturn([
            listPermission('grade.publish'),
            listPermission('attendance.edit'),
            listPermission('payment.approve'),
        ]);
        $this->permissionRepo->shouldNotReceive('findByCategoryIdOrCommon');

        $result = $this->useCase->execute('custom-uuid');

        expect($result)->toHaveCount(3);
    });

    it('throws RoleNotFoundException when roleUuid does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->with('missing-uuid')->once()->andReturn(null);

        expect(fn () => $this->useCase->execute('missing-uuid'))
            ->toThrow(RoleNotFoundException::class);
    });
});
