<?php

namespace App\Modules\Staff\Domain\Contracts;

use App\Modules\Staff\Domain\Entities\StaffPersonnelDetail;
use App\Modules\Staff\Domain\Entities\StaffPersonnelListItem;

interface StaffPersonnelReadRepositoryInterface
{
    /**
     * Return all Softlinkia staff users (is_staff = true) with their staff role.
     *
     * @return array<StaffPersonnelListItem>
     */
    public function list(): array;

    /**
     * Return the full detail of a staff member by UUID, or null when not found.
     */
    public function findByUuid(string $uuid): ?StaffPersonnelDetail;
}
