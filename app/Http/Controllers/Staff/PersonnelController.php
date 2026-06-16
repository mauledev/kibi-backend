<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Requests\Staff\CreatePersonnelRequest;
use App\Http\Resources\Staff\StaffMemberResource;
use App\Http\Resources\Staff\StaffPersonnelListResource;
use App\Http\Resources\Staff\StaffPersonnelResource;
use App\Http\Response\ApiResponse;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelInput;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelUseCase;
use App\Modules\Staff\Application\UseCases\GetPersonnel\GetPersonnelUseCase;
use App\Modules\Staff\Application\UseCases\ListPersonnel\ListPersonnelUseCase;
use App\Modules\Staff\Domain\Entities\WorkSchedule;
use App\Modules\Staff\Domain\Exceptions\InvalidStaffRoleException;
use App\Modules\Staff\Domain\Exceptions\PermissionNotAllowedException;
use App\Modules\Staff\Domain\Exceptions\PersonnelNotFoundException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    /**
     * List Backoffice staff members (paginated).
     * GET /staff/personnel
     *
     * Query params:
     *   page     — page number (default 1)
     *   per_page — items per page (default 20, max 100)
     *
     * Responds 200 with a paginated list (pagination under `meta.pagination`).
     */
    public function index(Request $request, ListPersonnelUseCase $useCase): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '20')));
        $page = max(1, (int) $request->query('page', '1'));

        $result = $useCase->execute($page, $perPage);

        $items = array_map(
            fn ($item) => (new StaffPersonnelListResource($item))->resolve(),
            $result['items'],
        );

        return ApiResponse::paginated($items, [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ]);
    }

    /**
     * Get a single Backoffice staff member by UUID.
     * GET /staff/personnel/{uuid}
     *
     * Responds 200 with the full member (role, personal data, work schedule,
     * effective permissions, requires_2fa). 404 when not found.
     */
    public function show(string $uuid, GetPersonnelUseCase $useCase): JsonResponse
    {
        try {
            return ApiResponse::success(new StaffPersonnelResource($useCase->execute($uuid)));
        } catch (PersonnelNotFoundException) {
            return ApiResponse::notFound();
        }
    }

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
