<?php

namespace App\Modules\User\Application\UseCases\ListUsers;

/**
 * Input DTO for the ListUsers use case.
 *
 * Tenant context is resolved internally by the repository via TenantContext injection.
 * The school scope is NOT taken directly from the request — it is derived from the
 * actor's authority by the use case. The controller only gathers the raw facts:
 * whether the actor is the tenant owner, the schools they can access, and the school
 * they explicitly requested (X-School-Uuid). The use case turns those into the final
 * scope and rejects a requested school the actor has no access to.
 *
 * @param  string|null  $search  Free-text search across name and email fields.
 * @param  array<int, string>  $roleSlugs  Filter to users who hold at least one of these role slugs.
 * @param  string|null  $status  Filter by lifecycle status (e.g. 'active', 'inactive').
 * @param  bool  $isOwner  True when the actor is the tenant owner (tenant-wide visibility).
 * @param  array<int, int>  $accessibleSchoolIds  School IDs the actor holds an active assignment in.
 *                                                Ignored when $isOwner is true.
 * @param  int|null  $requestedSchoolId  The school the actor explicitly selected via X-School-Uuid,
 *                                       already resolved to an internal id and verified to belong to
 *                                       the tenant. Null when no school header was sent.
 * @param  int  $perPage  Items per page (default 20).
 * @param  int  $page  One-based page index (default 1).
 */
final readonly class ListUsersInput
{
    /**
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, int>  $accessibleSchoolIds
     */
    public function __construct(
        public ?string $search = null,
        public array $roleSlugs = [],
        public ?string $status = null,
        public bool $isOwner = false,
        public array $accessibleSchoolIds = [],
        public ?int $requestedSchoolId = null,
        public int $perPage = 20,
        public int $page = 1,
    ) {}
}
