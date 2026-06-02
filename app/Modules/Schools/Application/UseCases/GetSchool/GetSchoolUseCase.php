<?php

namespace App\Modules\Schools\Application\UseCases\GetSchool;

use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

final class GetSchoolUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
    ) {}

    /**
     * @throws SchoolNotFoundException
     */
    public function execute(GetSchoolInput $input): School
    {
        $school = $this->repository->findByUuid($input->uuid);

        if ($school === null || $school->isDeleted()) {
            throw SchoolNotFoundException::withUuid($input->uuid);
        }

        return $school;
    }
}
