<?php

use App\Common\Audit\AuditLogger;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleInput;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;

describe('CreateRoleUseCase', function () {
    beforeEach(function () {
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLogger::class);
        $this->useCase = new CreateRoleUseCase($this->roleRepo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('creates a role when hierarchy_level is strictly greater than actor level', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            tenantId: 10,
            name: 'Director',
            slug: 'director',
            hierarchyLevel: 4,
        );

        $role = new Role(
            id: 99,
            publicId: 'public-uuid',
            tenantId: 10,
            name: 'Director',
            slug: 'director',
            hierarchyLevel: 4,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('create')
            ->once()
            ->with(10, 'Director', 'director', 4, false)
            ->andReturn($role);

        $this->audit->shouldReceive('log')
            ->once()
            ->with('role.create', 1, 99, null, null, Mockery::type('array'));

        $result = $this->useCase->execute($input);

        expect($result)->toBeInstanceOf(Role::class);
        expect($result->getSlug())->toBe('director');
    });

    it('throws HierarchyViolationException when hierarchy_level equals actor level', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            tenantId: 10,
            name: 'Director',
            slug: 'director',
            hierarchyLevel: 4,
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('throws HierarchyViolationException when hierarchy_level is less than actor level', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            tenantId: 10,
            name: 'Gestor',
            slug: 'gestor',
            hierarchyLevel: 3, // lower level = more privileged = violation
        );

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('never calls repo when hierarchy check fails', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 4,
            tenantId: 10,
            name: 'Director',
            slug: 'director',
            hierarchyLevel: 4,
        );

        $this->roleRepo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(HierarchyViolationException::class);
    });

    it('level 1 actor can create a role at level 2', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 1,
            tenantId: null,
            name: 'Owner',
            slug: 'owner',
            hierarchyLevel: 2,
        );

        $role = new Role(
            id: 1,
            publicId: 'owner-uuid',
            tenantId: null,
            name: 'Owner',
            slug: 'owner',
            hierarchyLevel: 2,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('create')
            ->once()
            ->andReturn($role);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result->getHierarchyLevel())->toBe(2);
    });

    it('always creates role with isSystemRole false', function () {
        $input = new CreateRoleInput(
            actorUserId: 1,
            actorHierarchyLevel: 3,
            tenantId: 10,
            name: 'Custom Role',
            slug: 'custom_role',
            hierarchyLevel: 5,
        );

        $role = new Role(
            id: 7,
            publicId: 'custom-uuid',
            tenantId: 10,
            name: 'Custom Role',
            slug: 'custom_role',
            hierarchyLevel: 5,
            isSystemRole: false,
            permissions: [],
            createdAt: new DateTimeImmutable,
        );

        $this->roleRepo->shouldReceive('create')
            ->once()
            ->with(10, 'Custom Role', 'custom_role', 5, false)
            ->andReturn($role);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($input);

        expect($result->isSystemRole())->toBeFalse();
    });
});
