<?php

namespace App\Modules\Staff\Domain\Contracts;

use App\Modules\Staff\Domain\Entities\StaffPersonnelDetail;
use App\Modules\Staff\Domain\Entities\StaffPersonnelListItem;

interface StaffPersonnelReadRepositoryInterface
{
    /**
     * Return a page of Softlinkia staff users (is_staff = true) with their staff role.
     *
     * @return array{
     *     items: array<StaffPersonnelListItem>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     * }
     */
    public function list(int $page, int $perPage): array;

    /**
     * Return the full detail of a staff member by UUID, or null when not found.
     */
    public function findByUuid(string $uuid): ?StaffPersonnelDetail;
}
