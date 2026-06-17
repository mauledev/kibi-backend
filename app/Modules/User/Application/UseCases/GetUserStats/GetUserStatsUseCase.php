<?php

namespace App\Modules\User\Application\UseCases\GetUserStats;

use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Criteria\UserStatsCriteria;
use App\Modules\User\Domain\Exceptions\SchoolAccessDeniedException;

/**
 * Aggregate counts for the directory stats cards (total users, pending invitations).
 *
 * Read-only, no side effects. School visibility is authority-driven exactly like
 * ListUsers (owner → tenant-wide, optionally narrowed by X-School-Uuid; non-owner →
 * only their accessible schools, and a requested school must be within that set).
 * The result is independent of the list's search / role-multiselect filters: it is
 * the stable headline figure for the directory scope.
 */
final class GetUserStatsUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    /**
     * @return array{total: int, pending: int}
     *
     * @throws SchoolAccessDeniedException When a non-owner requests a school outside their access.
     */
    public function execute(GetUserStatsInput $input): array
    {
        return $this->repository->getStats(
            new UserStatsCriteria(
                roleSlugs: $input->roleSlugs,
                schoolIds: $this->resolveSchoolScope($input),
            )
        );
    }

    /**
     * Translate the actor's authority into the concrete school scope. Mirrors
     * ListUsersUseCase so both endpoints scope identically.
     *
     * @return array<int, int>|null null → tenant-wide; [] → none; [ids] → restricted.
     *
     * @throws SchoolAccessDeniedException
     */
    private function resolveSchoolScope(GetUserStatsInput $input): ?array
    {
        if ($input->isOwner) {
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

        return array_values($input->accessibleSchoolIds);
    }
}
