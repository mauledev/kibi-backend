<?php

namespace App\Modules\Roles\Domain\Contracts;

interface SchoolRepositoryInterface
{
    /**
     * Return the internal school id for the given UUID, scoped to the current tenant.
     * Returns null when no matching (non-deleted) school exists within this tenant.
     */
    public function findIdByUuid(string $uuid): ?int;
}
