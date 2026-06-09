<?php

namespace App\Modules\Staff\Infrastructure\Repositories;

use App\Models\StaffWorkSchedule as StaffWorkScheduleModel;
use App\Modules\Staff\Domain\Contracts\StaffWorkScheduleRepositoryInterface;
use App\Modules\Staff\Domain\Entities\WorkSchedule;

class EloquentStaffWorkScheduleRepository implements StaffWorkScheduleRepositoryInterface
{
    /** {@inheritDoc} */
    public function create(int $userId, WorkSchedule $schedule): void
    {
        StaffWorkScheduleModel::create([
            'user_id' => $userId,
            'timezone' => $schedule->getTimezone(),
            'days' => $schedule->getDays(),
            'start_time' => $schedule->getStartTime(),
            'end_time' => $schedule->getEndTime(),
        ]);
    }
}
