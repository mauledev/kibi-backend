<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\Role as RoleModel;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use DateTimeImmutable;

class EloquentStaffRoleRepository implements RoleRepositoryInterface
{
    /** {@inheritDoc} */
    public function findAll(): array
    {
        $models = RoleModel::with('permissions')
            ->where('is_system_role', true)
            ->get();

        return $models->map(fn (RoleModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where('is_system_role', true)
            ->where('uuid', $uuid)
            ->withTrashed()
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where('is_system_role', true)
            ->withTrashed()
            ->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where('is_system_role', true)
            ->where('slug', $slug)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function create(
        ?int $tenantId,
        ?int $categoryId,
        string $name,
        string $slug,
        int $hierarchyLevel,
        bool $isSystemRole,
    ): Role {
        $model = RoleModel::create([
            'tenant_id' => null,
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'hierarchy_level' => $hierarchyLevel,
            'is_system_role' => true,
        ]);

        $model->load('permissions');

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function update(Role $role): Role
    {
        $model = RoleModel::where('is_system_role', true)->findOrFail($role->getId());

        $model->update(['name' => $role->getName()]);
        $model->load('permissions');

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function delete(string $uuid): bool
    {
        return RoleModel::where('is_system_role', true)
            ->where('uuid', $uuid)
            ->delete() > 0;
    }

    /** {@inheritDoc} */
    public function findActiveRolesForUser(int $userId): array
    {
        $models = RoleModel::with('permissions')
            ->join('user_role_assignments', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where('user_role_assignments.user_id', $userId)
            ->whereNull('user_role_assignments.revoked_at')
            ->where('roles.is_system_role', true)
            ->select('roles.*')
            ->get();

        return $models->map(fn (RoleModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function attachPermission(int $roleId, int $permissionId): void
    {
        RoleModel::where('is_system_role', true)
            ->findOrFail($roleId)
            ->permissions()
            ->syncWithoutDetaching([$permissionId]);
    }

    /** {@inheritDoc} */
    public function detachPermission(int $roleId, int $permissionId): void
    {
        RoleModel::where('is_system_role', true)
            ->findOrFail($roleId)
            ->permissions()
            ->detach($permissionId);
    }

    private function toDomain(RoleModel $model): Role
    {
        $permissions = $model->relationLoaded('permissions')
            ? $model->permissions->map(function (\App\Models\Permission $p): Permission {
                return new Permission(
                    id: $p->id,
                    uuid: $p->uuid,
                    categoryId: $p->category_id,
                    name: $p->name,
                    slug: $p->slug,
                    createdAt: new DateTimeImmutable($p->created_at?->toIso8601String() ?? 'now'),
                );
            })->all()
            : [];

        return new Role(
            id: $model->id,
            uuid: $model->uuid,
            tenantId: $model->tenant_id,
            categoryId: $model->category_id,
            name: $model->name,
            slug: $model->slug,
            hierarchyLevel: $model->hierarchy_level,
            isSystemRole: $model->is_system_role,
            requiresTwoFactor: (bool) $model->requires_2fa,
            permissions: $permissions,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
            deletedAt: $model->deleted_at !== null
                ? new DateTimeImmutable($model->deleted_at->toIso8601String())
                : null,
        );
    }

    /** {@inheritDoc} */
    public function countCustomRoles(int $tenantId): int
    {
        throw new \RuntimeException('EloquentStaffRoleRepository::countCustomRoles() is not applicable for staff roles.');
    }

    /** {@inheritDoc} */
    public function attachSchools(int $roleId, array $schoolIds): void
    {
        throw new \RuntimeException('EloquentStaffRoleRepository::attachSchools() is not applicable for staff roles.');
    }

    /** {@inheritDoc} */
    public function getCustomRolesLimit(int $tenantId): ?int
    {
        throw new \RuntimeException('EloquentStaffRoleRepository::getCustomRolesLimit() is not applicable for staff roles.');
    }

    /** {@inheritDoc} */
    public function setCustomRolesLimit(int $tenantId, int $limit): void
    {
        throw new \RuntimeException('EloquentStaffRoleRepository::setCustomRolesLimit() is not applicable for staff roles.');
    }
}
