<?php

namespace App\Modules\User\Domain\Contracts;

use App\Modules\User\Domain\Criteria\UserListCriteria;
use App\Modules\User\Domain\Criteria\UserStatsCriteria;
use App\Modules\User\Domain\Entities\User;

/**
 * Read-only repository contract for the User module.
 *
 * Scoping rules (enforced by every implementation):
 *  - Always filter by tenant_id = TenantContext::tenantId as the first predicate.
 *  - Always filter by is_staff = false — staff users are never returned from tenant queries.
 *  - Never expose the internal integer id in any return value.
 */
interface UserRepositoryInterface
{
    /**
     * Return a paginated slice of tenant users matching the given criteria.
     *
     * Tenant scope and is_staff = false are applied before any criteria filter.
     * Implementations must eager-load active role assignments with their role and
     * school relations to avoid N+1 queries when mapping roles to the entity.
     *
     * @return array{
     *     items: User[],
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     * }
     */
    public function findAllPaginated(UserListCriteria $criteria): array;

    /**
     * Find a single tenant user by their public UUID.
     *
     * Returns null when no user with that UUID exists within the current tenant scope.
     * Implementations must eager-load active role assignments with role and school.
     */
    public function findByUuid(string $uuid): ?User;

    /**
     * Aggregate counts for the directory stats cards, within the given scope.
     *
     * Tenant scope and is_staff = false are applied before any criteria filter.
     * No rows are loaded — implementations issue COUNT queries only.
     *
     * `pending` counts users with an unverified email (email_verified_at IS NULL),
     * mirroring the virtual `pending` status the API exposes for invited-not-yet-
     * activated accounts.
     *
     * @return array{total: int, pending: int}
     */
    public function getStats(UserStatsCriteria $criteria): array;
}
