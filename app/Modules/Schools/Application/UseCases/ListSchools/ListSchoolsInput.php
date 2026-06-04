<?php

namespace App\Modules\Schools\Application\UseCases\ListSchools;

use App\Modules\Schools\Domain\Enums\SchoolListFilter;

/**
 * Input DTO for the ListSchools use case.
 *
 * Tenant context is resolved internally by the repository via TenantContext
 * injection. The `statusFilter` narrows the result set by lifecycle state,
 * including soft-deleted (deactivated) schools.
 *
 * Allowed values are owned by {@see SchoolListFilter}. The default is
 * `SchoolListFilter::Active`; "include every row" is expressed exclusively
 * as `SchoolListFilter::All` — there is no null/Active ambiguity.
 */
final readonly class ListSchoolsInput
{
    public function __construct(
        public SchoolListFilter $statusFilter = SchoolListFilter::Active,
    ) {}
}
