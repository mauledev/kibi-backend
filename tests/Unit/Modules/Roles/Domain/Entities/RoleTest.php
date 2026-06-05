<?php

use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;

describe('Role entity', function () {
    function makeRole(array $overrides = []): Role
    {
        return new Role(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'uuid-1',
            tenantId: array_key_exists('tenantId', $overrides) ? $overrides['tenantId'] : 10,
            categoryId: array_key_exists('categoryId', $overrides) ? $overrides['categoryId'] : null,
            name: $overrides['name'] ?? 'Director',
            slug: $overrides['slug'] ?? 'director',
            hierarchyLevel: $overrides['hierarchyLevel'] ?? 4,
            isSystemRole: $overrides['isSystemRole'] ?? false,
            permissions: $overrides['permissions'] ?? [],
            createdAt: $overrides['createdAt'] ?? new DateTimeImmutable('2024-01-01'),
            deletedAt: array_key_exists('deletedAt', $overrides) ? $overrides['deletedAt'] : null,
        );
    }

    function makePermission(string $slug = 'grade.publish', string $uuid = 'perm-uuid-1'): Permission
    {
        return new Permission(
            id: 1,
            uuid: $uuid,
            categoryId: 1,
            name: 'Publish Grade',
            slug: $slug,
        );
    }

    it('exposes all read properties correctly', function () {
        $role = makeRole();

        expect($role->getId())->toBe(1);
        expect($role->getUuid())->toBe('uuid-1');
        expect($role->getTenantId())->toBe(10);
        expect($role->getName())->toBe('Director');
        expect($role->getSlug())->toBe('director');
        expect($role->getHierarchyLevel())->toBe(4);
        expect($role->isSystemRole())->toBeFalse();
        expect($role->getPermissions())->toBeArray()->toBeEmpty();
    });

    it('reports not deleted when deletedAt is null', function () {
        $role = makeRole(['deletedAt' => null]);

        expect($role->isDeleted())->toBeFalse();
        expect($role->getDeletedAt())->toBeNull();
    });

    it('reports deleted when deletedAt is set', function () {
        $role = makeRole(['deletedAt' => new DateTimeImmutable('2025-01-01')]);

        expect($role->isDeleted())->toBeTrue();
        expect($role->getDeletedAt())->not->toBeNull();
    });

    it('returns true for hasPermission when slug is present', function () {
        $permission = makePermission('grade.publish');
        $role = makeRole(['permissions' => [$permission]]);

        expect($role->hasPermission('grade.publish'))->toBeTrue();
    });

    it('returns false for hasPermission when slug is absent', function () {
        $permission = makePermission('grade.publish');
        $role = makeRole(['permissions' => [$permission]]);

        expect($role->hasPermission('payment.approve'))->toBeFalse();
    });

    it('returns false for hasPermission when permissions list is empty', function () {
        $role = makeRole(['permissions' => []]);

        expect($role->hasPermission('grade.publish'))->toBeFalse();
    });

    it('setPermissions replaces the permission list', function () {
        $role = makeRole(['permissions' => []]);
        $permission = makePermission('role.view');

        $role->setPermissions([$permission]);

        expect($role->getPermissions())->toHaveCount(1);
        expect($role->hasPermission('role.view'))->toBeTrue();
    });

    it('rename updates the name', function () {
        $role = makeRole(['name' => 'Old Name']);
        $role->rename('New Name');

        expect($role->getName())->toBe('New Name');
    });

    it('allows null tenantId for system roles', function () {
        $role = makeRole(['tenantId' => null, 'isSystemRole' => true]);

        expect($role->getTenantId())->toBeNull();
        expect($role->isSystemRole())->toBeTrue();
    });

    it('hasPermission checks by exact slug match', function () {
        $permission = makePermission('manage.permissions');
        $role = makeRole(['permissions' => [$permission]]);

        expect($role->hasPermission('manage.permissions'))->toBeTrue();
        expect($role->hasPermission('manage'))->toBeFalse();
        expect($role->hasPermission('permissions'))->toBeFalse();
    });

    describe('isCustomRole()', function () {
        it('returns true when tenantId is set, categoryId is null, and slug is not owner or gestor_escuelas', function () {
            $role = makeRole(['tenantId' => 5, 'categoryId' => null, 'slug' => 'my_custom_role']);

            expect($role->isCustomRole())->toBeTrue();
        });

        it('returns false for owner slug even when tenantId is set and categoryId is null', function () {
            $role = makeRole(['tenantId' => 5, 'categoryId' => null, 'slug' => 'owner']);

            expect($role->isCustomRole())->toBeFalse();
        });

        it('returns false for gestor_escuelas slug even when tenantId is set and categoryId is null', function () {
            $role = makeRole(['tenantId' => 5, 'categoryId' => null, 'slug' => 'gestor_escuelas']);

            expect($role->isCustomRole())->toBeFalse();
        });

        it('returns false when categoryId is set', function () {
            $role = makeRole(['tenantId' => 5, 'categoryId' => 3, 'slug' => 'director']);

            expect($role->isCustomRole())->toBeFalse();
        });

        it('returns false when tenantId is null (system role)', function () {
            $role = makeRole(['tenantId' => null, 'categoryId' => null, 'slug' => 'some_role']);

            expect($role->isCustomRole())->toBeFalse();
        });
    });
});
