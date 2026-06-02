<?php

namespace App\Modules\Schools\Application\UseCases\ListSchools;

/**
 * Input DTO for the ListSchools use case.
 *
 * Currently empty — tenant context is resolved internally by the repository
 * via TenantContext injection. Add filter fields here as requirements grow
 * (e.g. status, search query).
 */
final readonly class ListSchoolsInput
{
    public function __construct() {}
}
