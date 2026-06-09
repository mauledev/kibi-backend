<?php

namespace App\Modules\Staff\Infrastructure\Repositories;

use App\Models\Role as RoleModel;
use App\Models\StaffWorkSchedule as StaffWorkScheduleModel;
use App\Models\User as UserModel;
use App\Modules\Staff\Domain\Contracts\StaffPersonnelReadRepositoryInterface;
use App\Modules\Staff\Domain\Entities\StaffPersonnelDetail;
use App\Modules\Staff\Domain\Entities\StaffPersonnelListItem;
use App\Modules\Staff\Domain\Entities\WorkSchedule;
use App\Modules\Staff\Domain\Enums\StaffRoleEnum;
use DateTimeImmutable;

class EloquentStaffPersonnelReadRepository implements StaffPersonnelReadRepositoryInterface
{
    /** {@inheritDoc} */
    public function list(): array
    {
        $users = UserModel::where('is_staff', true)
            ->orderByDesc('created_at')
            ->get();

        return $users->map(function (UserModel $user): StaffPersonnelListItem {
            $role = $this->staffRoleOf($user);

            return new StaffPersonnelListItem(
                uuid: $user->uuid,
                firstName: $user->first_name,
                lastNamePaternal: $user->last_name_paternal,
                lastNameMaternal: $user->last_name_maternal,
                email: $user->email,
                roleSlug: $role?->slug,
                roleName: $role?->name,
                status: $user->status,
                createdAt: $this->toImmutable($user->created_at?->toIso8601String()),
            );
        })->all();
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?StaffPersonnelDetail
    {
        $user = UserModel::where('is_staff', true)
            ->where('uuid', $uuid)
            ->first();

        if ($user === null) {
            return null;
        }

        $role = $this->staffRoleOf($user);
        $roleSlug = $role?->slug;

        $requires2fa = $roleSlug !== null
            ? (StaffRoleEnum::tryFrom($roleSlug)?->requires2fa() ?? false)
            : false;

        $schedule = StaffWorkScheduleModel::where('user_id', $user->id)->first();

        $workSchedule = $schedule !== null
            ? new WorkSchedule(
                timezone: $schedule->timezone,
                days: $schedule->days,
                startTime: substr((string) $schedule->start_time, 0, 5),
                endTime: substr((string) $schedule->end_time, 0, 5),
            )
            : null;

        return new StaffPersonnelDetail(
            uuid: $user->uuid,
            roleSlug: $roleSlug,
            roleName: $role?->name,
            firstName: $user->first_name,
            lastNamePaternal: $user->last_name_paternal,
            lastNameMaternal: $user->last_name_maternal,
            email: $user->email,
            phone: $user->phone,
            status: $user->status,
            workSchedule: $workSchedule,
            permissions: array_values($user->activePermissions(null)),
            requires2fa: $requires2fa,
            createdAt: $this->toImmutable($user->created_at?->toIso8601String()),
        );
    }

    /**
     * Resolve the user's staff role (is_system_role = true) from active assignments.
     */
    private function staffRoleOf(UserModel $user): ?RoleModel
    {
        return $user->activeRoles()->first(fn (RoleModel $role) => (bool) $role->is_system_role);
    }

    private function toImmutable(?string $iso): DateTimeImmutable
    {
        return new DateTimeImmutable($iso ?? 'now');
    }
}
