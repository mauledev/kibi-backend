<?php

namespace App\Modules\Student\Application\UseCases\ListStudents;

/**
 * Input DTO for listing students.
 *
 * Tenant context is resolved internally by the repository via TenantContext injection.
 * School visibility is authority-driven — the use case derives the concrete scope from
 * the actor's authority, not directly from the request header.
 *
 * @param  string|null  $search  Free-text search across first_name, last_name_paternal, email.
 * @param  bool  $isOwner  True when the actor is the tenant owner (tenant-wide visibility).
 * @param  array<int, int>  $accessibleSchoolIds  School IDs the actor can access. Ignored when isOwner is true.
 * @param  int|null  $requestedSchoolId  The school the actor selected via X-School-Uuid, resolved to internal id.
 * @param  int  $perPage  Items per page.
 * @param  int  $page  One-based page index.
 */
final readonly class ListStudentsInput
{
    /**
     * @param  array<int, int>  $accessibleSchoolIds
     */
    public function __construct(
        public ?string $search = null,
        public bool $isOwner = false,
        public array $accessibleSchoolIds = [],
        public ?int $requestedSchoolId = null,
        public int $perPage = 20,
        public int $page = 1,
    ) {}
}
