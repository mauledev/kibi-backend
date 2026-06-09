<?php

namespace App\Modules\Staff\Domain\Contracts;

use App\Modules\Staff\Domain\Entities\WorkSchedule;

interface StaffWorkScheduleRepositoryInterface
{
    /**
     * Persist the work schedule for a staff user (one schedule per user).
     */
    public function create(int $userId, WorkSchedule $schedule): void;
}
