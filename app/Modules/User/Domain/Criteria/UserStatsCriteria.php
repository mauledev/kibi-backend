<?php

namespace App\Modules\User\Domain\Criteria;

/**
 * Criteria object accepted by UserRepositoryInterface::getStats.
 *
 * Holds only the scope that defines "which users the directory counts" — role
 * slugs and school scope. It deliberately omits search/status/pagination: the
 * stats are stable headline figures for the directory, not a reflection of the
 * list's current filters.
 *
 * Lives in Domain (framework-free) because the repository interface does.
 */
final readonly class UserStatsCriteria
{
    /**
     * @param  array<int, string>  $roleSlugs  Restrict the count to users holding an active
     *                                         assignment with at least one of these slugs.
     *                                         Empty array means no role restriction.
     * @param  array<int, int>|null  $schoolIds  School scope. Three states:
     *                                           - null  → no school restriction (tenant-wide; owner).
     *                                           - []    → no accessible school → counts are zero.
     *                                           - [ids] → restrict to users with an active assignment
     *                                           in any of these schools.
     */
    public function __construct(
        public array $roleSlugs = [],
        public ?array $schoolIds = null,
    ) {}
}
