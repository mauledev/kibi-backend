<?php

namespace App\Modules\Schools\Application\UseCases\GetUserSchools;

use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Criteria\SchoolListCriteria;
use App\Modules\Schools\Domain\Entities\School;

/**
 * Returns the schools a user can operate in (for the SchoolGate / school switcher).
 *
 * Access is strictly role-based: a school is returned ONLY if the user holds an
 * active role assignment in it (i.e. its id is in `accessibleSchoolIds`). No role
 * in a school = no access. A user with no school-scoped assignment gets `[]` —
 * this includes the owner, whose tenant-wide authority is handled by the gate's
 * superuser short-circuit, not by this endpoint.
 */
final class GetUserSchoolsUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<int, int>  $accessibleSchoolIds  School ids where the user holds an
     *                                                active role assignment (User::accessibleSchoolIds()).
     * @return array<School>
     */
    public function execute(array $accessibleSchoolIds): array
    {
        if ($accessibleSchoolIds === []) {
            return [];
        }

        $schools = $this->repository->findAll(new SchoolListCriteria);

        return array_values(array_filter(
            $schools,
            fn (School $school): bool => in_array($school->getId(), $accessibleSchoolIds, true),
        ));
    }
}
