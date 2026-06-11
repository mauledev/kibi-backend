<?php

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleInput;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\CustomRoleLimitExceededException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;

describe('CreateRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->schoolRepo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->useCase = new CreateRoleUseCase($this->roleRepo, $this->schoolRepo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function createBuildRole(string $name = 'My Role'): Role
    {
        return new Role(
            id: 99,
            uuid: 'custom-uuid',
            tenantId: 1,
            categoryId: null,
            name: $name,
            slug: 'my_role',
            hierarchyLevel: 99,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
        );
    }

    it('throws HierarchyViolationException when actor slug is not owner or school_manager', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorSlug: 'director',
            tenantId: 10,
            name: 'My Custom Role',
            schoolUuids: [],
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws CustomRoleLimitExceededException when custom_roles_limit is null', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            tenantId: 1,
            name: 'Custom Role',
            schoolUuids: [],
        );

        $this->roleRepo->shouldReceive('getCustomRolesLimit')->with(1)->andReturn(null);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(CustomRoleLimitExceededException::class);
    });

    it('throws CustomRoleLimitExceededException when limit is reached', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            tenantId: 1,
            name: 'Custom Role',
            schoolUuids: [],
        );

        $this->roleRepo->shouldReceive('getCustomRolesLimit')->with(1)->andReturn(3);
        $this->roleRepo->shouldReceive('countCustomRoles')->with(1)->andReturn(3);

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(CustomRoleLimitExceededException::class);
    });

    it('creates a custom role with category_id null when limit not exceeded', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorSlug: 'owner',
            tenantId: 1,
            name: 'My Custom Role',
            schoolUuids: [],
        );

        $this->roleRepo->shouldReceive('getCustomRolesLimit')->with(1)->andReturn(5);
        $this->roleRepo->shouldReceive('countCustomRoles')->with(1)->andReturn(2);

        $createdRole = createBuildRole('My Custom Role');

        $this->roleRepo->shouldReceive('create')
            ->once()
            ->with(1, null, 'My Custom Role', Mockery::type('string'), 99, false)
            ->andReturn($createdRole);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(Role::class);
        expect($result->getCategoryId())->toBeNull();
        expect($result->isCustomRole())->toBeTrue();
    });

    it('associates schools when schoolUuids are provided', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorSlug: 'school_manager',
            tenantId: 1,
            name: 'Multi School Role',
            schoolUuids: ['school-uuid-1', 'school-uuid-2'],
        );

        $this->roleRepo->shouldReceive('getCustomRolesLimit')->with(1)->andReturn(10);
        $this->roleRepo->shouldReceive('countCustomRoles')->with(1)->andReturn(0);

        $createdRole = createBuildRole('Multi School Role');

        $this->roleRepo->shouldReceive('create')
            ->once()
            ->andReturn($createdRole);

        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-uuid-1')->andReturn(10);
        $this->schoolRepo->shouldReceive('findIdByUuid')->with('school-uuid-2')->andReturn(20);

        $this->roleRepo->shouldReceive('attachSchools')
            ->once()
            ->with(99, Mockery::type('array'));

        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute($input);
    });
});
