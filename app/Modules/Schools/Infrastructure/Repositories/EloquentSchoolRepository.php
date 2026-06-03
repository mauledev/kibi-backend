<?php

namespace App\Modules\Schools\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\School as SchoolModel;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Criteria\SchoolListCriteria;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Enums\SchoolListFilter;
use DateTimeImmutable;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /** {@inheritDoc} */
    public function findAll(SchoolListCriteria $criteria): array
    {
        $query = SchoolModel::where('tenant_id', $this->context->tenantId);

        match ($criteria->status) {
            SchoolListFilter::Active => $query->where('status', SchoolListFilter::Active->value),
            SchoolListFilter::Deactivated => $query->onlyTrashed(),
            SchoolListFilter::All => $query->withTrashed(),
        };

        return $query->get()
            ->map(fn (SchoolModel $m) => $this->toDomain($m))
            ->all();
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?School
    {
        $model = SchoolModel::where('tenant_id', $this->context->tenantId)
            ->where('uuid', $uuid)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function existsBySlug(string $slug): bool
    {
        return SchoolModel::where('tenant_id', $this->context->tenantId)
            ->where('slug', $slug)
            ->exists();
    }

    /** {@inheritDoc} */
    public function create(
        string $name,
        string $slug,
        ?array $address,
        ?string $phone,
        string $status,
    ): School {
        $model = SchoolModel::create([
            'tenant_id' => $this->context->tenantId,
            'name' => $name,
            'slug' => $slug,
            'address' => $address,
            'phone' => $phone,
            'status' => $status,
        ]);

        // Refresh to load DB-generated defaults (e.g. uuid from gen_random_uuid())
        $model->refresh();

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function update(School $school): School
    {
        $model = SchoolModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($school->getId());

        $model->update([
            'name' => $school->getName(),
            'phone' => $school->getPhone(),
            'address' => $school->getAddress(),
            'status' => $school->getStatus(),
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function softDelete(int $schoolId): void
    {
        SchoolModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($schoolId)
            ->delete();
    }

    private function toDomain(SchoolModel $model): School
    {
        return new School(
            id: $model->id,
            uuid: $model->uuid,
            tenantId: $model->tenant_id,
            name: $model->name,
            slug: $model->slug,
            address: $model->address,
            phone: $model->phone,
            status: $model->status,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
            updatedAt: new DateTimeImmutable($model->updated_at?->toIso8601String() ?? 'now'),
            deletedAt: $model->deleted_at !== null
                ? new DateTimeImmutable($model->deleted_at->toIso8601String())
                : null,
        );
    }
}
