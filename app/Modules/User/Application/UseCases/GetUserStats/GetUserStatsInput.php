<?php

namespace App\Modules\User\Application\UseCases\GetUserStats;

/**
 * Input DTO for the GetUserStats use case.
 *
 * Like ListUsers, the school scope is NOT taken directly from the request — it is
 * derived from the actor's authority by the use case. The controller only gathers
 * the raw facts: whether the actor is the owner, the schools they can access, the
 * school they explicitly requested (X-School-Uuid), and the role scope that defines
 * the directory (non-family slugs, sent by the client).
 *
 * @param  array<int, string>  $roleSlugs  Role slugs that define the directory scope.
 * @param  bool  $isOwner  True when the actor is the tenant owner (tenant-wide visibility).
 * @param  array<int, int>  $accessibleSchoolIds  School IDs the actor holds an active assignment in.
 *                                                 Ignored when $isOwner is true.
 * @param  int|null  $requestedSchoolId  School selected via X-School-Uuid (already resolved to an
 *                                        internal id and verified to belong to the tenant), or null.
 */
final readonly class GetUserStatsInput
{
    /**
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, int>  $accessibleSchoolIds
     */
    public function __construct(
        public array $roleSlugs = [],
        public bool $isOwner = false,
        public array $accessibleSchoolIds = [],
        public ?int $requestedSchoolId = null,
    ) {}
}
