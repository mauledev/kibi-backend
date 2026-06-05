<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\School;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Return the internal school id for the given UUID, scoped to the current tenant.
     * Returns null when no matching school exists within this tenant.
     */
    public function findIdByUuid(string $uuid): ?int
    {
        $value = School::where('tenant_id', $this->context->tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->value('id');

        return $value !== null ? (int) $value : null;
    }
}
