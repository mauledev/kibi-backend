<?php

namespace App\Http\Controllers\Schools;

use App\Common\School\SchoolContext;
use App\Http\Controller;
use App\Http\Requests\Schools\CreateSchoolRequest;
use App\Http\Requests\Schools\UpdateSchoolRequest;
use App\Http\Resources\Schools\SchoolResource;
use App\Http\Response\ApiResponse;
use App\Modules\Schools\Application\UseCases\CreateSchool\CreateSchoolInput;
use App\Modules\Schools\Application\UseCases\CreateSchool\CreateSchoolUseCase;
use App\Modules\Schools\Application\UseCases\DeactivateSchool\DeactivateSchoolInput;
use App\Modules\Schools\Application\UseCases\DeactivateSchool\DeactivateSchoolUseCase;
use App\Modules\Schools\Application\UseCases\GetCurrentSchool\GetCurrentSchoolInput;
use App\Modules\Schools\Application\UseCases\GetCurrentSchool\GetCurrentSchoolUseCase;
use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolInput;
use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolUseCase;
use App\Modules\Schools\Application\UseCases\ListSchools\ListSchoolsInput;
use App\Modules\Schools\Application\UseCases\ListSchools\ListSchoolsUseCase;
use App\Modules\Schools\Application\UseCases\UpdateSchool\UpdateSchoolInput;
use App\Modules\Schools\Application\UseCases\UpdateSchool\UpdateSchoolUseCase;
use App\Modules\Schools\Domain\Exceptions\SchoolAlreadyExistsException;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    /**
     * GET /schools — List all schools of the authenticated tenant.
     */
    public function index(Request $request, ListSchoolsUseCase $useCase): JsonResponse
    {
        $this->authorize('school.view');

        $schools = $useCase->execute(new ListSchoolsInput);

        return ApiResponse::success(SchoolResource::collection($schools)->resolve());
    }

    /**
     * GET /schools/{uuid} — Get a single school by its public UUID.
     */
    public function show(Request $request, string $uuid, GetSchoolUseCase $useCase): JsonResponse
    {
        $this->authorize('school.view');

        try {
            $school = $useCase->execute(new GetSchoolInput($uuid));

            return ApiResponse::success((new SchoolResource($school))->resolve());
        } catch (SchoolNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * GET /school — Return the school the user is currently operating in,
     * identified by the X-School-Uuid header resolved by SchoolMiddleware.
     */
    public function currentSchool(Request $request, GetCurrentSchoolUseCase $useCase): JsonResponse
    {
        try {
            $school = $useCase->execute(new GetCurrentSchoolInput(
                schoolId: app(SchoolContext::class)->schoolId,
            ));

            return ApiResponse::success((new SchoolResource($school))->resolve());
        } catch (SchoolNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * POST /schools — Create a new school within the current tenant.
     */
    public function store(CreateSchoolRequest $request, CreateSchoolUseCase $useCase): JsonResponse
    {
        $this->authorize('school.create');

        try {
            $school = $useCase->execute(new CreateSchoolInput(
                actorUserId: $request->user()->id,
                name: $request->validated('name'),
                slug: $request->validated('slug'),
                address: $request->validated('address'),
                phone: $request->validated('phone'),
            ));

            return ApiResponse::created((new SchoolResource($school))->resolve());
        } catch (SchoolAlreadyExistsException $e) {
            return ApiResponse::conflict($e->getMessage());
        }
    }

    /**
     * PUT /schools/{uuid} — Update mutable fields of a school. Partial
     * semantics: only the keys present in the request body are applied.
     */
    public function update(
        UpdateSchoolRequest $request,
        string $uuid,
        UpdateSchoolUseCase $useCase,
    ): JsonResponse {
        $this->authorize('school.update');

        try {
            $school = $useCase->execute(new UpdateSchoolInput(
                actorUserId: $request->user()->id,
                uuid: $uuid,
                hasName: $request->has('name'),
                name: $request->input('name'),
                hasPhone: $request->has('phone'),
                phone: $request->input('phone'),
                hasAddress: $request->has('address'),
                address: $request->input('address'),
            ));

            return ApiResponse::success((new SchoolResource($school))->resolve());
        } catch (SchoolNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * POST /schools/{uuid}/deactivate — Soft-delete a school. After this call
     * the school is hidden from list and lookup endpoints. The row stays in
     * the database for audit / FK integrity.
     */
    public function deactivate(
        Request $request,
        string $uuid,
        DeactivateSchoolUseCase $useCase,
    ): JsonResponse {
        $this->authorize('school.update');

        try {
            $useCase->execute(new DeactivateSchoolInput(
                actorUserId: $request->user()->id,
                uuid: $uuid,
            ));

            return ApiResponse::success(null, 'Escuela desactivada exitosamente');
        } catch (SchoolNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
