<?php

namespace App\Modules\Tenant\Infrastructure\Repositories;

use App\Models\Tenant as TenantModel;
use App\Modules\Auth\Domain\Entities\User as UserEntity;
use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;

class EloquentTenantRepository implements TenantRepositoryInterface
{
    /** {@inheritDoc} */
    public function findBySlug(string $slug): ?Tenant
    {
        $model = TenantModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Find a tenant by slug and eager-load its owner user.
     * Used after creation to return the full entity with embedded owner.
     */
    public function findBySlugWithOwner(string $slug): Tenant
    {
        $model = TenantModel::where('slug', $slug)
            ->with('owner')
            ->firstOrFail();

        return $this->toDomainWithOwner($model);
    }

    /** {@inheritDoc} */
    public function create(string $name, string $slug, int $ownerId): Tenant
    {
        $model = TenantModel::create([
            'name' => $name,
            'slug' => $slug,
            'owner_id' => $ownerId,
            'status' => 'pending',
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function activate(int $id): void
    {
        TenantModel::where('id', $id)->update(['status' => 'active']);
    }

    /** {@inheritDoc} */
    public function listPaginated(int $perPage, int $page): array
    {
        $paginator = TenantModel::with('owner')
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => array_map(fn (TenantModel $m) => $this->toDomainWithOwner($m), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Tenant
    {
        $model = TenantModel::where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findByUuidWithOwner(string $uuid): ?Tenant
    {
        $model = TenantModel::where('uuid', $uuid)->with('owner')->first();

        return $model ? $this->toDomainWithOwner($model) : null;
    }

    /** {@inheritDoc} */
    public function update(int $id, string $name, string $slug, string $status): Tenant
    {
        /** @var TenantModel $model */
        $model = TenantModel::findOrFail($id);
        $model->update([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function delete(int $id): void
    {
        TenantModel::where('id', $id)->delete();
    }

    private function toDomain(TenantModel $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            slug: $model->slug,
            status: $model->status,
            ownerId: (int) $model->owner_id,
            createdAt: $model->created_at?->toIso8601String(),
        );
    }

    private function toDomainWithOwner(TenantModel $model): Tenant
    {
        $ownerEntity = null;

        if ($model->owner !== null) {
            $ownerModel = $model->owner;

            $ownerEntity = new UserEntity(
                id: $ownerModel->id,
                uuid: $ownerModel->uuid,
                email: $ownerModel->email,
                firstName: $ownerModel->first_name,
                lastNamePaternal: $ownerModel->last_name_paternal,
                lastNameMaternal: $ownerModel->last_name_maternal,
                passwordHash: $ownerModel->password_hash,
                status: $ownerModel->status,
                isStaff: (bool) $ownerModel->is_staff,
                tenantId: $ownerModel->tenant_id,
            );
        }

        return new Tenant(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            slug: $model->slug,
            status: $model->status,
            ownerId: (int) $model->owner_id,
            owner: $ownerEntity,
            createdAt: $model->created_at?->toIso8601String(),
        );
    }
}
