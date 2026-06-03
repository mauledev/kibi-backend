<?php

namespace App\Modules\Schools\Domain\Contracts;

use App\Modules\Schools\Domain\Criteria\SchoolListCriteria;
use App\Modules\Schools\Domain\Entities\School;

interface SchoolRepositoryInterface
{
    /**
     * Return schools belonging to the current tenant, narrowed by the given
     * {@see SchoolListCriteria}.
     *
     * Tenant scoping is applied internally by the repository implementation
     * via the injected TenantContext — callers must not pass a tenant ID.
     *
     * A criteria with `status = null` (the default) returns every non-deleted
     * row without filtering by the `status` column — preserving the legacy
     * contract.
     *
     * @return array<School>
     */
    public function findAll(SchoolListCriteria $criteria): array;

    /**
     * Find a single school by its public UUID within the current tenant scope.
     */
    public function findByUuid(string $uuid): ?School;

    /**
     * Check whether a school with the given slug already exists within the
     * current tenant scope. Tenant scoping is applied internally.
     */
    public function existsBySlug(string $slug): bool;

    /**
     * Persist a new school within the current tenant scope and return its
     * domain entity. The tenant_id is sourced from the injected TenantContext.
     *
     * @param  array<string, mixed>|null  $address
     */
    public function create(
        string $name,
        string $slug,
        ?array $address,
        ?string $phone,
        string $status,
    ): School;

    /**
     * Persist mutable fields of an existing school and return the refreshed
     * domain entity. Tenant scoping is enforced internally.
     */
    public function update(School $school): School;

    /**
     * Soft-delete a school by setting its deleted_at timestamp. This is the
     * canonical "deactivate" operation — soft-deleted schools are excluded
     * from every other query in this repository.
     */
    public function softDelete(int $schoolId): void;
}
