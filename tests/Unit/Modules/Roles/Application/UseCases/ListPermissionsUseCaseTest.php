<?php

use App\Modules\Roles\Application\UseCases\ListPermissions\ListPermissionsUseCase;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;

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
});
