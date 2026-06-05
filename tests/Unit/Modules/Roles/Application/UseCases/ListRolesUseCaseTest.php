<?php

use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesInput;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

describe('ListRolesUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->useCase = new ListRolesUseCase($this->roleRepo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function listRole(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'some-uuid',
            tenantId: $overrides['tenantId'] ?? 1,
            categoryId: null,
            name: $overrides['name'] ?? 'Some Role',
            slug: $overrides['slug'] ?? 'some_role',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 5,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('returns all roles from repository when excludeDeleted is false', function () {
        $active = listRole(['uuid' => 'active-uuid', 'slug' => 'active_role']);
        $deleted = listRole(['uuid' => 'deleted-uuid', 'slug' => 'deleted_role', 'deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findAll')->once()->andReturn([$active, $deleted]);

        $input = new ListRolesInput(excludeDeleted: false);
        $result = $this->useCase->execute($input);

        expect($result)->toHaveCount(2);
    });

    it('filters out soft-deleted roles when excludeDeleted is true', function () {
        $active = listRole(['uuid' => 'active-uuid', 'slug' => 'active_role']);
        $deleted = listRole(['uuid' => 'deleted-uuid', 'slug' => 'deleted_role', 'deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findAll')->once()->andReturn([$active, $deleted]);

        $input = new ListRolesInput(excludeDeleted: true);
        $result = $this->useCase->execute($input);

        expect($result)->toHaveCount(1);
        expect($result[0]->getUuid())->toBe('active-uuid');
    });

    it('returns empty array when no roles exist', function () {
        $this->roleRepo->shouldReceive('findAll')->once()->andReturn([]);

        $input = new ListRolesInput;
        $result = $this->useCase->execute($input);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns all roles when none are deleted and excludeDeleted is true', function () {
        $roleA = listRole(['uuid' => 'r-a', 'slug' => 'role_a']);
        $roleB = listRole(['uuid' => 'r-b', 'slug' => 'role_b']);

        $this->roleRepo->shouldReceive('findAll')->once()->andReturn([$roleA, $roleB]);

        $input = new ListRolesInput(excludeDeleted: true);
        $result = $this->useCase->execute($input);

        expect($result)->toHaveCount(2);
    });

    it('excludeDeleted defaults to true', function () {
        $active = listRole(['uuid' => 'a-uuid', 'slug' => 'active']);
        $deleted = listRole(['uuid' => 'd-uuid', 'slug' => 'deleted', 'deletedAt' => new DateTimeImmutable]);

        $this->roleRepo->shouldReceive('findAll')->once()->andReturn([$active, $deleted]);

        $input = new ListRolesInput; // defaults to excludeDeleted: true
        $result = $this->useCase->execute($input);

        expect($result)->toHaveCount(1);
    });
});
