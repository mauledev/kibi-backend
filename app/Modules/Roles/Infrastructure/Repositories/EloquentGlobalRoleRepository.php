<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\Role as RoleModel;
use App\Models\User as UserModel;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use DateTimeImmutable;
use RuntimeException;

/**
 * Role repository that operates without a TenantContext.
 *
 * Used exclusively by UseCases that run outside the tenant request cycle
 * (e.g. ActivateAccountUseCase). Scopes role reads by the user's own
 * tenant_id derived from their user record.
 */
class EloquentGlobalRoleRepository implements RoleRepositoryInterface
{
    /** {@inheritDoc} */
    public function findActiveRolesForUser(int $userId): array
    {
        $user = UserModel::find($userId);

        $models = RoleModel::with('permissions')
            ->join('user_role_assignments', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where(function ($q) use ($user) {
                $q->where('roles.tenant_id', $user?->tenant_id)
                    ->orWhereNull('roles.tenant_id');
            })
            ->where('user_role_assignments.user_id', $userId)
            ->whereNull('user_role_assignments.revoked_at')
            ->select('roles.*')
            ->get();

        return $models->map(fn (RoleModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findAll(): array
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::findAll() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Role
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::findByUuid() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Role
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::findById() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Role
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::findBySlug() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function create(
        ?int $tenantId,
        string $name,
        string $slug,
        int $hierarchyLevel,
        bool $isSystemRole,
    ): Role {
        throw new RuntimeException('EloquentGlobalRoleRepository::create() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function update(Role $role): Role
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::update() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function delete(string $uuid): bool
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::delete() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function attachPermission(int $roleId, int $permissionId): void
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::attachPermission() is not supported without TenantContext.');
    }

    /** {@inheritDoc} */
    public function detachPermission(int $roleId, int $permissionId): void
    {
        throw new RuntimeException('EloquentGlobalRoleRepository::detachPermission() is not supported without TenantContext.');
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
            name: $model->name,
            slug: $model->slug,
            hierarchyLevel: $model->hierarchy_level,
            isSystemRole: $model->is_system_role,
            permissions: $permissions,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
            deletedAt: $model->deleted_at !== null
                ? new DateTimeImmutable($model->deleted_at->toIso8601String())
                : null,
        );
    }
}
