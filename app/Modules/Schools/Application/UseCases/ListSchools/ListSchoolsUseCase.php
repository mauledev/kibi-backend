<?php

namespace App\Modules\Schools\Application\UseCases\ListSchools;

use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;

final class ListSchoolsUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
    ) {}

    /**
     * Return all schools belonging to the current tenant.
     *
     * @return array<School>
     */
    public function execute(ListSchoolsInput $input): array
    {
        return $this->repository->findAll();
    }
}
