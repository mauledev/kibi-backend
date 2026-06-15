<?php

namespace App\Modules\Student\Domain\Criteria;

/**
 * Criteria object accepted by StudentRepositoryInterface::findAllPaginated.
 *
 * Wrapping all query parameters in a single immutable object means new filters
 * can be added later without breaking the repository contract signature.
 *
 * This class lives in Domain because the repository interface lives there — it
 * must remain free of framework dependencies.
 *
 * @param  string|null  $search  Free-text search applied against first_name, last_name_paternal, email.
 * @param  int|null  $schoolId  When set, restricts to students enrolled in this specific school.
 * @param  array<int, int>  $accessibleSchoolIds  Schools the actor has access to. Empty when actor is owner (sees all).
 * @param  bool  $isOwner  True when the actor is the tenant owner (tenant-wide visibility).
 * @param  int  $perPage  Number of items per page.
 * @param  int  $page  One-based page number.
 */
final class StudentListCriteria
{
    /**
     * @param  array<int, int>  $accessibleSchoolIds
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $schoolId = null,
        public readonly array $accessibleSchoolIds = [],
        public readonly bool $isOwner = false,
        public readonly int $perPage = 20,
        public readonly int $page = 1,
    ) {}
}
