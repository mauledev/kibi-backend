<?php

namespace App\Modules\User\Application\UseCases\ListUsers;

use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Criteria\UserListCriteria;
use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Exceptions\SchoolAccessDeniedException;

/**
 * Returns a paginated list of tenant users matching the supplied filters.
 *
 * This use case is read-only — it has no side effects and writes nothing to
 * audit_logs. Tenant context and is_staff scoping are handled by the repository.
 *
 * School visibility is authority-driven, not client-driven:
 *  - Owner      → tenant-wide. May narrow to a single school via X-School-Uuid.
 *  - Non-owner  → only the schools they hold an active assignment in. Without a
 *                 header they see every accessible school at once; with a header
 *                 the requested school must be within their accessible set.
 *
 * This closes the leak where any actor could omit X-School-Uuid and fall back to
 * a tenant-wide listing.
 */
final class ListUsersUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    /**
     * Execute the use case.
     *
     * @return array{items: User[], total: int, per_page: int, current_page: int, last_page: int}
     *
     * @throws SchoolAccessDeniedException When a non-owner requests a school outside their access.
     */
    public function execute(ListUsersInput $input): array
    {
        return $this->repository->findAllPaginated(
            new UserListCriteria(
                search: $input->search,
                roleSlugs: $input->roleSlugs,
                status: $input->status,
                schoolIds: $this->resolveSchoolScope($input),
                perPage: $input->perPage,
                page: $input->page,
            )
        );
    }

    /**
     * Translate the actor's authority into the concrete school scope passed to
     * the repository.
     *
     * Return value semantics (see {@see UserListCriteria}):
     *  - null  → tenant-wide, no school restriction.
     *  - []    → no accessible school, the query returns nothing.
     *  - [ids] → restrict to these schools.
     *
     * @return array<int, int>|null
     *
     * @throws SchoolAccessDeniedException
     */
    private function resolveSchoolScope(ListUsersInput $input): ?array
    {
        if ($input->isOwner) {
            // Owner sees the whole tenant, optionally narrowed to one school.
            return $input->requestedSchoolId !== null
                ? [$input->requestedSchoolId]
                : null;
        }

        if ($input->requestedSchoolId !== null) {
            if (! in_array($input->requestedSchoolId, $input->accessibleSchoolIds, true)) {
                throw new SchoolAccessDeniedException;
            }

            return [$input->requestedSchoolId];
        }

        // No header: every school the actor can access (possibly empty → no results).
        return array_values($input->accessibleSchoolIds);
    }
}
