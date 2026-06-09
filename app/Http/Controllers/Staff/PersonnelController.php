<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Requests\Staff\CreatePersonnelRequest;
use App\Http\Resources\Staff\StaffMemberResource;
use App\Http\Response\ApiResponse;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelInput;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelUseCase;
use App\Modules\Staff\Domain\Entities\WorkSchedule;
use App\Modules\Staff\Domain\Exceptions\InvalidStaffRoleException;
use App\Modules\Staff\Domain\Exceptions\PermissionNotAllowedException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use Illuminate\Http\JsonResponse;

class PersonnelController extends Controller
{
    /**
     * Create a Backoffice staff member.
     * POST /staff/personnel
     *
     * Responds 201 with the created member (role, personal data, effective
     * permissions, requires_2fa). 409 when the email is taken, 422 for an
     * invalid role or a permission outside the role catalogue.
     */
    public function store(CreatePersonnelRequest $request, CreatePersonnelUseCase $useCase): JsonResponse
    {
        try {
            $member = $useCase->execute(new CreatePersonnelInput(
                role: $request->validated('role'),
                firstName: $request->validated('personal_data.first_name'),
                lastNamePaternal: $request->validated('personal_data.last_name_paternal'),
                lastNameMaternal: $request->validated('personal_data.last_name_maternal'),
                email: $request->validated('personal_data.email'),
                phone: $request->validated('personal_data.phone'),
                workSchedule: new WorkSchedule(
                    timezone: $request->validated('work_schedule.timezone'),
                    days: $request->validated('work_schedule.days'),
                    startTime: $request->validated('work_schedule.start_time'),
                    endTime: $request->validated('work_schedule.end_time'),
                ),
                permissions: $request->validated('permissions'),
                createdBy: $request->user()?->id,
            ));

            return ApiResponse::created(new StaffMemberResource($member));
        } catch (StaffEmailAlreadyTakenException $e) {
            return ApiResponse::conflict($e->getMessage(), ['personal_data.email' => [$e->getMessage()]]);
        } catch (InvalidStaffRoleException|PermissionNotAllowedException|StaffRoleNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
