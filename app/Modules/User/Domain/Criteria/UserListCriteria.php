<?php

namespace App\Modules\User\Domain\Criteria;

/**
 * Criteria object accepted by UserRepositoryInterface::findAllPaginated.
 *
 * Wrapping all query parameters in a single immutable object means new filters
 * (sorting, date ranges) can be added later without breaking the repository
 * contract signature or any existing callers.
 *
 * This class lives in Domain because the repository interface lives there — it
 * must remain free of framework dependencies.
 */
final readonly class UserListCriteria
{
    /**
     * @param  string|null  $search  Free-text search applied against first_name,
     *                               last_name_paternal, last_name_maternal, and email
     *                               using a case-insensitive ILIKE match. Null means
     *                               no search filter is applied.
     * @param  array<int, string>  $roleSlugs  Filter by one or more role slugs. Only users
     *                                         who hold an active assignment with at least one
     *                                         of these slugs are returned. Empty array means
     *                                         no role filter is applied.
     * @param  string|null  $status  Filter by the users.status column value (e.g. 'active',
     *                               'inactive'). Null means no status filter is applied.
     * @param  bool  $unassigned  When true, return only users that have NO active role
     *                            assignment. Takes precedence over $roleSlugs and the school
     *                            scope (a role-less user belongs to no school).
     * @param  array<int, int>|null  $schoolIds  School scope for the query. Three states:
     *                                           - null  → no school restriction (tenant-wide; owner context).
     *                                           - []    → no accessible school → the query returns no users.
     *                                           - [ids] → restrict to users with an active assignment in any
     *                                           of these schools. Also controls which assignments are
     *                                           shown in the roles collection of returned entities:
     *                                           assignments in these schools plus tenant-level
     *                                           assignments (school_id IS NULL).
     * @param  int  $perPage  Number of items per page. Defaults to 20.
     * @param  int  $page  One-based page number. Defaults to 1.
     */
    public function __construct(
        public ?string $search = null,
        public array $roleSlugs = [],
        public ?string $status = null,
        public bool $unassigned = false,
        public ?array $schoolIds = null,
        public int $perPage = 20,
        public int $page = 1,
    ) {}
}
