<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\Role as RoleModel;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /** {@inheritDoc} */
    public function findAll(): array
    {
        $models = RoleModel::with('permissions')
            ->where(function ($q) {
                $q->where('tenant_id', $this->context->tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->get();

        return $models->map(fn (RoleModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where(function ($q) {
                $q->where('tenant_id', $this->context->tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->where('uuid', $uuid)
            ->withTrashed()
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where(function ($q) {
                $q->where('tenant_id', $this->context->tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->withTrashed()
            ->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Role
    {
        $model = RoleModel::with('permissions')
            ->where(function ($q) {
                $q->where('tenant_id', $this->context->tenantId)
                    ->orWhereNull('tenant_id');
            })
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
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'hierarchy_level' => $hierarchyLevel,
            'is_system_role' => $isSystemRole,
        ]);

        // Refresh to load DB-generated defaults (e.g. uuid from gen_random_uuid())
        $model->refresh();
        $model->load('permissions');

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function update(Role $role): Role
    {
        $model = RoleModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($role->getId());

        $model->update(['name' => $role->getName()]);
        $model->load('permissions');

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function delete(string $uuid): bool
    {
        return RoleModel::where('tenant_id', $this->context->tenantId)
            ->where('uuid', $uuid)
            ->delete() > 0;
    }

    /** {@inheritDoc} */
    public function findActiveRolesForUser(int $userId): array
    {
        $models = RoleModel::with('permissions')
            ->join('user_role_assignments', 'user_role_assignments.role_id', '=', 'roles.id')
            ->where(function ($q) {
                $q->where('roles.tenant_id', $this->context->tenantId)
                    ->orWhereNull('roles.tenant_id');
            })
            ->where('user_role_assignments.user_id', $userId)
            ->whereNull('user_role_assignments.revoked_at')
            ->select('roles.*')
            ->get();

        return $models->map(fn (RoleModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function attachPermission(int $roleId, int $permissionId): void
    {
        RoleModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($roleId)
            ->permissions()
            ->syncWithoutDetaching([$permissionId]);
    }

    /** {@inheritDoc} */
    public function detachPermission(int $roleId, int $permissionId): void
    {
        RoleModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($roleId)
            ->permissions()
            ->detach($permissionId);
    }

    /** {@inheritDoc} */
    public function countCustomRoles(int $tenantId): int
    {
        return (int) RoleModel::where('tenant_id', $tenantId)
            ->whereNull('category_id')
            ->whereNotIn('slug', ['owner', 'school_manager'])
            ->count();
    }

    /** {@inheritDoc} */
    public function attachSchools(int $roleId, array $schoolIds): void
    {
        foreach ($schoolIds as $schoolId) {
            DB::table('custom_role_schools')->insertOrIgnore([
                'role_id' => $roleId,
                'school_id' => $schoolId,
            ]);
        }
    }

    /** {@inheritDoc} */
    public function getCustomRolesLimit(int $tenantId): ?int
    {
        $limit = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('custom_roles_limit');

        return $limit !== null ? (int) $limit : null;
    }

    /** {@inheritDoc} */
    public function setCustomRolesLimit(int $tenantId, int $limit): void
    {
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['custom_roles_limit' => $limit]);
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
            permissions: $permissions,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
            deletedAt: $model->deleted_at !== null
                ? new DateTimeImmutable($model->deleted_at->toIso8601String())
                : null,
        );
    }
}
