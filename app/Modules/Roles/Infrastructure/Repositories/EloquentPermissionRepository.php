<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Modules\Roles\Domain\Contracts\PermissionRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use DateTimeImmutable;

class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    /** {@inheritDoc} */
    public function findAll(): array
    {
        $models = PermissionModel::join('permission_categories', 'permission_categories.id', '=', 'permissions.category_id')
            ->whereNull('permission_categories.deleted_at')
            ->select('permissions.*')
            ->get();

        return $models->map(fn (PermissionModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Permission
    {
        $model = PermissionModel::where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Permission
    {
        $model = PermissionModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Permission
    {
        $model = PermissionModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findByRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $models = PermissionModel::join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', $roleIds)
            ->select('permissions.*')
            ->distinct()
            ->get();

        return $models->map(fn (PermissionModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findCategoryScope(int $categoryId): ?string
    {
        $scope = PermissionCategory::find($categoryId)?->scope;

        return $scope ?? null;
    }

    /** {@inheritDoc} */
    public function findByCategoryId(int $categoryId): array
    {
        $models = PermissionModel::where('category_id', $categoryId)->get();

        return $models->map(fn (PermissionModel $m) => $this->toDomain($m))->all();
    }

    private function toDomain(PermissionModel $model): Permission
    {
        return new Permission(
            id: $model->id,
            uuid: $model->uuid,
            categoryId: $model->category_id,
            name: $model->name,
            slug: $model->slug,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
        );
    }
}
