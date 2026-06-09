<?php

namespace App\Http\Resources\Staff;

use App\Modules\Staff\Domain\Entities\StaffPersonnelDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffPersonnelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StaffPersonnelDetail $member */
        $member = $this->resource;
        $schedule = $member->getWorkSchedule();

        return [
            'uuid' => $member->getUuid(),
            'role' => $member->getRoleSlug() !== null
                ? ['slug' => $member->getRoleSlug(), 'name' => $member->getRoleName()]
                : null,
            'personal_data' => [
                'first_name' => $member->getFirstName(),
                'last_name_paternal' => $member->getLastNamePaternal(),
                'last_name_maternal' => $member->getLastNameMaternal(),
                'email' => $member->getEmail(),
                'phone' => $member->getPhone(),
            ],
            'status' => $member->getStatus(),
            'work_schedule' => $schedule !== null
                ? [
                    'timezone' => $schedule->getTimezone(),
                    'days' => $schedule->getDays(),
                    'start_time' => $schedule->getStartTime(),
                    'end_time' => $schedule->getEndTime(),
                ]
                : null,
            'permissions' => $member->getPermissions(),
            'requires_2fa' => $member->requires2fa(),
            'created_at' => $member->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
