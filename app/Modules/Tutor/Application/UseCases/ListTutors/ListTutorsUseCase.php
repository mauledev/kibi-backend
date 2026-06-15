<?php

namespace App\Modules\Tutor\Application\UseCases\ListTutors;

use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Criteria\TutorListCriteria;
use App\Modules\Tutor\Domain\Entities\Tutor;

/**
 * Returns a paginated list of tutors in the current tenant matching the supplied filters.
 *
 * School visibility is authority-driven:
 *  - Owner      → tenant-wide. May narrow to one school via X-School-Uuid.
 *  - Non-owner  → only schools they hold an active assignment in.
 *
 * This use case is read-only and writes nothing to audit_logs.
 */
final class ListTutorsUseCase
{
    public function __construct(
        private readonly TutorRepositoryInterface $repository,
    ) {}

    /**
     * Execute the use case.
     *
     * @return array{items: Tutor[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function execute(ListTutorsInput $input): array
    {
        [$schoolId, $accessibleSchoolIds] = $this->resolveSchoolScope($input);

        return $this->repository->findAllPaginated(
            new TutorListCriteria(
                search: $input->search,
                requestedSchoolId: $schoolId,
                accessibleSchoolIds: $accessibleSchoolIds,
                isOwner: $input->isOwner,
                perPage: $input->perPage,
                page: $input->page,
            )
        );
    }

    /**
     * Translate the actor's authority into the concrete school scope.
     *
     * Returns a tuple of [schoolId, accessibleSchoolIds] where:
     *  - schoolId is the specific school requested, or null for all accessible schools.
     *  - accessibleSchoolIds is the actor's full list of accessible schools (empty means owner).
     *
     * @return array{int|null, array<int, int>}
     */
    private function resolveSchoolScope(ListTutorsInput $input): array
    {
        if ($input->isOwner) {
            // Owner sees the whole tenant, optionally narrowed to one school.
            return [$input->requestedSchoolId, []];
        }

        if ($input->requestedSchoolId !== null) {
            // Non-owner with explicit school: that school only (access validated by middleware).
            return [$input->requestedSchoolId, $input->accessibleSchoolIds];
        }

        // No school header: all accessible schools.
        return [null, $input->accessibleSchoolIds];
    }
}
