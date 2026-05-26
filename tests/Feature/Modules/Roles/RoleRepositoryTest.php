<?php

use App\Common\Tenant\TenantContext;
use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentRoleRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    });

    function bindTenantContext(Tenant $tenant): void
    {
        app()->instance(TenantContext::class, new TenantContext(tenantId: $tenant->id));
    }

    function makeRepo(): RoleRepositoryInterface
    {
        return app(RoleRepositoryInterface::class);
    }

    describe('tenant isolation', function () {
        it('findAll returns only roles for the current tenant plus system roles', function () {
            bindTenantContext($this->tenantA);

            $roleA = RoleModel::factory()->forTenant($this->tenantA)->atLevel(4)->create(['name' => 'Director A', 'slug' => 'director_a']);
            $roleB = RoleModel::factory()->forTenant($this->tenantB)->atLevel(4)->create(['name' => 'Director B', 'slug' => 'director_b']);
            $systemRole = RoleModel::factory()->system()->atLevel(1)->create(['name' => 'Superadmin', 'slug' => 'superadmin']);

            $roles = makeRepo()->findAll();

            $slugs = array_map(fn (Role $r) => $r->getSlug(), $roles);

            expect($slugs)->toContain('director_a');
            expect($slugs)->toContain('superadmin');
            expect($slugs)->not->toContain('director_b');
        });

        it('findByPublicId returns null when role belongs to a different tenant', function () {
            bindTenantContext($this->tenantA);

            $roleB = RoleModel::factory()->forTenant($this->tenantB)->atLevel(5)->create(['slug' => 'other_role']);

            $result = makeRepo()->findByPublicId($roleB->public_id);

            expect($result)->toBeNull();
        });

        it('findByPublicId returns role when it belongs to current tenant', function () {
            bindTenantContext($this->tenantA);

            $roleA = RoleModel::factory()->forTenant($this->tenantA)->atLevel(4)->create(['name' => 'Director', 'slug' => 'director']);

            $result = makeRepo()->findByPublicId($roleA->public_id);

            expect($result)->not->toBeNull();
            expect($result->getPublicId())->toBe($roleA->public_id);
        });

        it('create persists a role and returns it', function () {
            bindTenantContext($this->tenantA);

            $role = makeRepo()->create(
                tenantId: $this->tenantA->id,
                name: 'New Role',
                slug: 'new_role',
                hierarchyLevel: 5,
                isSystemRole: false,
            );

            expect($role)->toBeInstanceOf(Role::class);
            expect($role->getName())->toBe('New Role');
            expect($role->getSlug())->toBe('new_role');
            expect($role->getTenantId())->toBe($this->tenantA->id);

            $this->assertDatabaseHas('roles', ['slug' => 'new_role', 'tenant_id' => $this->tenantA->id]);
        });

        it('delete soft-deletes the role', function () {
            bindTenantContext($this->tenantA);

            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(4)->create(['slug' => 'to_delete']);

            $result = makeRepo()->delete($role->public_id);

            expect($result)->toBeTrue();
            $this->assertSoftDeleted('roles', ['id' => $role->id]);
        });

        it('findByPublicId returns soft-deleted role via withTrashed', function () {
            bindTenantContext($this->tenantA);

            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(4)->create(['slug' => 'deleted_role']);
            $role->delete();

            $found = makeRepo()->findByPublicId($role->public_id);

            expect($found)->not->toBeNull();
            expect($found->isDeleted())->toBeTrue();
        });
    });

    describe('findActiveRolesForUser', function () {
        it('returns active roles for a user', function () {
            bindTenantContext($this->tenantA);

            $user = User::factory()->for($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(5)->create(['slug' => 'docente']);

            UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

            $roles = makeRepo()->findActiveRolesForUser($user->id);

            $slugs = array_map(fn (Role $r) => $r->getSlug(), $roles);
            expect($slugs)->toContain('docente');
        });

        it('does not return revoked assignments', function () {
            bindTenantContext($this->tenantA);

            $user = User::factory()->for($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(5)->create(['slug' => 'revoked_role']);

            UserRoleAssignment::factory()->forUser($user)->forRole($role)->revoked()->create();

            $roles = makeRepo()->findActiveRolesForUser($user->id);

            $slugs = array_map(fn (Role $r) => $r->getSlug(), $roles);
            expect($slugs)->not->toContain('revoked_role');
        });
    });

    describe('attachPermission / detachPermission', function () {
        it('attachPermission links a permission to a role', function () {
            bindTenantContext($this->tenantA);

            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(5)->create(['slug' => 'attach_test']);
            $category = PermissionCategory::factory()->system()->create();
            $permission = PermissionModel::factory()->withSlug('grade.publish')->create(['category_id' => $category->id]);

            makeRepo()->attachPermission($role->id, $permission->id);

            $this->assertDatabaseHas('role_permissions', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        });

        it('detachPermission unlinks a permission from a role', function () {
            bindTenantContext($this->tenantA);

            $role = RoleModel::factory()->forTenant($this->tenantA)->atLevel(5)->create(['slug' => 'detach_test']);
            $category = PermissionCategory::factory()->system()->create();
            $permission = PermissionModel::factory()->withSlug('grade.delete')->create(['category_id' => $category->id]);

            $role->permissions()->attach($permission->id);

            makeRepo()->detachPermission($role->id, $permission->id);

            $this->assertDatabaseMissing('role_permissions', [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        });
    });
});
