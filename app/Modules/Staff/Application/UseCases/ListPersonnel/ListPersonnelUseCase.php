<?php

namespace App\Modules\Staff\Application\UseCases\ListPersonnel;

use App\Modules\Staff\Domain\Contracts\StaffPersonnelReadRepositoryInterface;
use App\Modules\Staff\Domain\Entities\StaffPersonnelListItem;

class ListPersonnelUseCase
{
    public function __construct(
        private readonly StaffPersonnelReadRepositoryInterface $personnel,
    ) {}

    /**
     * @return array<StaffPersonnelListItem>
     */
    public function execute(): array
    {
        return $this->personnel->list();
    }
}
