<?php

use App\Modules\Roles\Application\UseCases\GetRole\GetRoleInput;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;

describe('GetRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->useCase = new GetRoleUseCase($this->roleRepo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function getRoleEntity(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 10,
            uuid: $overrides['uuid'] ?? 'role-uuid',
            tenantId: array_key_exists('tenantId', $overrides) ? $overrides['tenantId'] : 1,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 4,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: $overrides['permissions'] ?? [],
            createdAt: new DateTimeImmutable,
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('throws RoleNotFoundException when role does not exist', function () {
        $this->roleRepo->shouldReceive('findByUuid')->once()->with('nonexistent')->andReturn(null);

        $input = new GetRoleInput(uuid: 'nonexistent');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('throws RoleNotFoundException when role is soft-deleted', function () {
        $role = getRoleEntity(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new GetRoleInput(uuid: 'role-uuid');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('returns the role when it exists and is not deleted', function () {
        $role = getRoleEntity();

        $this->roleRepo->shouldReceive('findByUuid')->once()->with('role-uuid')->andReturn($role);

        $input = new GetRoleInput(uuid: 'role-uuid');
        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(Role::class);
        expect($result->getUuid())->toBe('role-uuid');
        expect($result->getName())->toBe('Director');
    });

    it('returns role with its permissions', function () {
        $perm = new Permission(id: 1, uuid: 'perm-uuid', categoryId: 1, name: 'View Role', slug: 'role.view');
        $role = getRoleEntity(['permissions' => [$perm]]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new GetRoleInput(uuid: 'role-uuid');
        $result = $this->useCase->execute($input);

        expect($result->getPermissions())->toHaveCount(1);
        expect($result->getPermissions()[0]->getSlug())->toBe('role.view');
    });

    it('returns system role when it exists', function () {
        $role = getRoleEntity(['tenantId' => null, 'isSystemRole' => true]);

        $this->roleRepo->shouldReceive('findByUuid')->once()->andReturn($role);

        $input = new GetRoleInput(uuid: 'role-uuid');
        $result = $this->useCase->execute($input);

        expect($result->isSystemRole())->toBeTrue();
        expect($result->getTenantId())->toBeNull();
    });
});
