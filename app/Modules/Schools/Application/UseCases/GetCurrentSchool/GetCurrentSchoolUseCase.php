<?php

namespace App\Modules\Schools\Application\UseCases\GetCurrentSchool;

use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

final class GetCurrentSchoolUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
    ) {}

    /**
     * Retrieve the school identified by its internal ID (from SchoolContext).
     *
     * @throws SchoolNotFoundException
     */
    public function execute(GetCurrentSchoolInput $input): School
    {
        $school = $this->repository->findById($input->schoolId);

        if ($school === null || $school->isDeleted()) {
            throw SchoolNotFoundException::withId($input->schoolId);
        }

        return $school;
    }
}
