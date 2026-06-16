<?php

namespace App\Modules\Staff\Application\UseCases\GetPersonnel;

use App\Modules\Staff\Domain\Contracts\StaffPersonnelReadRepositoryInterface;
use App\Modules\Staff\Domain\Entities\StaffPersonnelDetail;
use App\Modules\Staff\Domain\Exceptions\PersonnelNotFoundException;

class GetPersonnelUseCase
{
    public function __construct(
        private readonly StaffPersonnelReadRepositoryInterface $personnel,
    ) {}

    /**
     * @throws PersonnelNotFoundException When no staff member matches the UUID.
     */
    public function execute(string $uuid): StaffPersonnelDetail
    {
        $member = $this->personnel->findByUuid($uuid);

        if ($member === null) {
            throw new PersonnelNotFoundException($uuid);
        }

        return $member;
    }
}
