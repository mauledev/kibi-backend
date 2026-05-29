<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\Tenant;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Infrastructure\Repositories\EloquentPermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentPermissionRepository', function () {
    beforeEach(function () {
        $this->repo = new EloquentPermissionRepository;
    });

    describe('findAll', function () {
        it('returns all permissions under system categories (school_id IS NULL)', function () {
            $systemCategory = PermissionCategory::factory()->system()->create();
            $permA = PermissionModel::factory()->withSlug('grade.publish')->create(['category_id' => $systemCategory->id]);
            $permB = PermissionModel::factory()->withSlug('payment.approve')->create(['category_id' => $systemCategory->id]);

            $results = $this->repo->findAll();
            $slugs = array_map(fn (Permission $p) => $p->getSlug(), $results);

            expect($slugs)->toContain('grade.publish');
            expect($slugs)->toContain('payment.approve');
        });

        it('returns empty array when no system permissions exist', function () {
            $results = $this->repo->findAll();

            expect($results)->toBeArray()->toBeEmpty();
        });

        it('returns Permission domain entities', function () {
            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('role.view')->create(['category_id' => $category->id]);

            $results = $this->repo->findAll();

            expect($results[0])->toBeInstanceOf(Permission::class);
        });
    });

    describe('findByUuid', function () {
        it('returns permission by uuid', function () {
            $category = PermissionCategory::factory()->system()->create();
            $model = PermissionModel::factory()->withSlug('role.create')->create(['category_id' => $category->id]);

            $result = $this->repo->findByUuid($model->uuid);

            expect($result)->not->toBeNull();
            expect($result->getUuid())->toBe($model->uuid);
            expect($result->getSlug())->toBe('role.create');
        });

        it('returns null when uuid does not exist', function () {
            $result = $this->repo->findByUuid('00000000-0000-0000-0000-000000000000');

            expect($result)->toBeNull();
        });
    });

    describe('findById', function () {
        it('returns permission by internal id', function () {
            $category = PermissionCategory::factory()->system()->create();
            $model = PermissionModel::factory()->withSlug('user.view')->create(['category_id' => $category->id]);

            $result = $this->repo->findById($model->id);

            expect($result)->not->toBeNull();
            expect($result->getId())->toBe($model->id);
        });

        it('returns null when id does not exist', function () {
            $result = $this->repo->findById(99999);

            expect($result)->toBeNull();
        });
    });

    describe('findBySlug', function () {
        it('returns permission by slug', function () {
            $category = PermissionCategory::factory()->system()->create();
            PermissionModel::factory()->withSlug('payment.view')->create(['category_id' => $category->id]);

            $result = $this->repo->findBySlug('payment.view');

            expect($result)->not->toBeNull();
            expect($result->getSlug())->toBe('payment.view');
        });

        it('returns null when slug does not exist', function () {
            $result = $this->repo->findBySlug('nonexistent.permission');

            expect($result)->toBeNull();
        });
    });

    describe('findByRoleIds', function () {
        it('returns all permissions for given role ids', function () {
            $tenant = Tenant::factory()->create();
            $category = PermissionCategory::factory()->system()->create();
            $permA = PermissionModel::factory()->withSlug('grade.delete')->create(['category_id' => $category->id]);
            $permB = PermissionModel::factory()->withSlug('grade.view')->create(['category_id' => $category->id]);

            $roleA = RoleModel::factory()->forTenant($tenant)->atLevel(4)->create(['slug' => 'role_a_perm']);
            $roleB = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'role_b_perm']);

            $roleA->permissions()->attach($permA->id);
            $roleB->permissions()->attach($permB->id);

            $results = $this->repo->findByRoleIds([$roleA->id, $roleB->id]);
            $slugs = array_map(fn (Permission $p) => $p->getSlug(), $results);

            expect($slugs)->toContain('grade.delete');
            expect($slugs)->toContain('grade.view');
        });

        it('deduplicates permissions shared by multiple roles', function () {
            $tenant = Tenant::factory()->create();
            $category = PermissionCategory::factory()->system()->create();
            $perm = PermissionModel::factory()->withSlug('shared.permission')->create(['category_id' => $category->id]);

            $roleA = RoleModel::factory()->forTenant($tenant)->atLevel(4)->create(['slug' => 'dedup_role_a']);
            $roleB = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'dedup_role_b']);

            $roleA->permissions()->attach($perm->id);
            $roleB->permissions()->attach($perm->id);

            $results = $this->repo->findByRoleIds([$roleA->id, $roleB->id]);

            $slugs = array_map(fn (Permission $p) => $p->getSlug(), $results);
            expect(array_count_values($slugs)['shared.permission'])->toBe(1);
        });

        it('returns empty array when role IDs list is empty', function () {
            $results = $this->repo->findByRoleIds([]);

            expect($results)->toBeArray()->toBeEmpty();
        });

        it('returns empty array when roles have no permissions', function () {
            $tenant = Tenant::factory()->create();
            $role = RoleModel::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'no_perm_role']);

            $results = $this->repo->findByRoleIds([$role->id]);

            expect($results)->toBeArray()->toBeEmpty();
        });
    });
});
