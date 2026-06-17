<?php

namespace App\Http\Controllers\Tutor;

use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Tutor\CreateTutorRequest;
use App\Http\Requests\Tutor\ListTutorsRequest;
use App\Http\Requests\Tutor\UpdateTutorRequest;
use App\Http\Resources\Tutor\TutorDetailResource;
use App\Http\Resources\Tutor\TutorListResource;
use App\Http\Response\ApiResponse;
use App\Models\School;
use App\Models\Tenant as TenantModel;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Tutor\Application\UseCases\CreateTutor\CreateTutorInput;
use App\Modules\Tutor\Application\UseCases\CreateTutor\CreateTutorUseCase;
use App\Modules\Tutor\Application\UseCases\GetTutor\GetTutorInput;
use App\Modules\Tutor\Application\UseCases\GetTutor\GetTutorUseCase;
use App\Modules\Tutor\Application\UseCases\LinkTutorToStudent\LinkTutorToStudentInput;
use App\Modules\Tutor\Application\UseCases\LinkTutorToStudent\LinkTutorToStudentUseCase;
use App\Modules\Tutor\Application\UseCases\ListTutors\ListTutorsInput;
use App\Modules\Tutor\Application\UseCases\ListTutors\ListTutorsUseCase;
use App\Modules\Tutor\Application\UseCases\UpdateTutor\UpdateTutorInput;
use App\Modules\Tutor\Application\UseCases\UpdateTutor\UpdateTutorUseCase;
use App\Modules\Tutor\Domain\Exceptions\StudentAlreadyLinkedToTutorException;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TutorController handles CRUD operations and student linking for tutors.
 *
 * index       → ListTutorsUseCase          (GET  /tutors)
 * show        → GetTutorUseCase            (GET  /tutors/{uuid})
 * store       → CreateTutorUseCase         (POST /tutors — requires X-School-Uuid)
 * update      → UpdateTutorUseCase         (PUT  /tutors/{uuid})
 * linkStudent → LinkTutorToStudentUseCase  (POST /tutors/{tutorUuid}/students/{studentUuid})
 *
 * Routes use the user's UUID as the public identifier.
 * Authorization: index/show require 'user.view'; store/linkStudent require 'user.create';
 * update requires 'user.update'.
 */
class TutorController extends Controller
{
    /**
     * List tutors in the current tenant, with optional filters.
     *
     * GET /tutors
     * Query params:
     *   q        — free-text search (name / email)
     *   page     — page number (default 1)
     *   per_page — items per page (default 20, max 100)
     *
     * School visibility is authority-driven:
     *   - Owner     → all tenant tutors; X-School-Uuid optionally narrows to one school.
     *   - Non-owner → only schools they hold an active assignment in.
     *
     * Responds 200 with a paginated list of tutors.
     */
    public function index(
        ListTutorsRequest $request,
        ListTutorsUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_VIEW->value);

        $actor = $request->user();
        $isOwner = $tenant->ownerId === $actor->id;

        $requestedSchoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        $result = $useCase->execute(new ListTutorsInput(
            search: $request->validated('q') ?: null,
            isOwner: $isOwner,
            accessibleSchoolIds: $isOwner ? [] : $actor->accessibleSchoolIds(),
            requestedSchoolId: $requestedSchoolId,
            perPage: (int) ($request->validated('per_page') ?? 20),
            page: (int) ($request->validated('page') ?? 1),
        ));

        $items = TutorListResource::collection($result['items'])->resolve();

        return ApiResponse::paginated($items, [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ]);
    }

    /**
     * Get a single tutor by their user UUID.
     *
     * GET /tutors/{uuid}
     *
     * Responds 200 with the full tutor detail.
     * Responds 404 when the UUID does not exist within the current tenant.
     * Responds 403 when the authenticated user lacks 'user.view'.
     */
    public function show(string $uuid, GetTutorUseCase $useCase): JsonResponse
    {
        $this->authorize(PermissionSlug::USER_VIEW->value);

        try {
            $tutor = $useCase->execute(new GetTutorInput(userUuid: $uuid));

            return ApiResponse::success((new TutorDetailResource($tutor))->resolve());
        } catch (TutorNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Create a new tutor.
     *
     * POST /tutors
     * Requires X-School-Uuid header — a tutor must belong to a school.
     *
     * Creates a pending user account, assigns the 'tutor' role in the given school,
     * creates the tutor profile, and sends a magic link activation email.
     *
     * Responds 201 with the created tutor's detail.
     * Responds 409 when the email is already registered.
     * Responds 403 on a hierarchy / role-exclusion violation.
     * Responds 422 when school context is missing.
     */
    public function store(
        CreateTutorRequest $request,
        CreateTutorUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_CREATE->value);

        $schoolUuid = app()->bound(SchoolContext::class)
            ? School::where('id', app(SchoolContext::class)->schoolId)->value('uuid')
            : null;

        if ($schoolUuid === null) {
            return ApiResponse::error('School context required. Include X-School-Uuid header with a valid school UUID.', 422);
        }

        $tenantSlug = TenantModel::find($tenant->tenantId)->slug ?? '';

        try {
            $tutor = $useCase->execute(new CreateTutorInput(
                tenantId: $tenant->tenantId,
                tenantSlug: $tenantSlug,
                actorUuid: $request->user()->uuid,
                actorSlug: $request->user()->resolveActorSlug(),
                schoolUuid: $schoolUuid,
                email: $request->validated('email'),
                firstName: $request->validated('first_name'),
                lastNamePaternal: $request->validated('last_name_paternal'),
                lastNameMaternal: $request->validated('last_name_maternal'),
                phone: $request->validated('phone'),
                occupation: $request->validated('occupation'),
            ));

            return ApiResponse::created((new TutorDetailResource($tutor))->resolve());
        } catch (\Throwable $e) {
            return match (true) {
                $e instanceof EmailAlreadyTakenException => ApiResponse::conflict($e->getMessage(), ['email' => [$e->getMessage()]]),
                $e instanceof HierarchyViolationException, $e instanceof RoleExclusionException => ApiResponse::forbidden($e->getMessage()),
                $e instanceof RoleNotFoundException => ApiResponse::notFound($e->getMessage()),
                default => throw $e,
            };
        }
    }

    /**
     * Update a tutor's identity and profile fields.
     *
     * PUT /tutors/{uuid}
     * The {uuid} parameter is the user's UUID.
     *
     * All fields are optional — only provided fields are updated.
     *
     * Responds 200 with the updated tutor detail.
     * Responds 404 when the tutor does not exist.
     * Responds 403 when the authenticated user lacks 'user.update'.
     */
    public function update(
        string $uuid,
        UpdateTutorRequest $request,
        UpdateTutorUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_UPDATE->value);

        try {
            $tutor = $useCase->execute(new UpdateTutorInput(
                userUuid: $uuid,
                firstName: $request->validated('first_name'),
                lastNamePaternal: $request->validated('last_name_paternal'),
                lastNameMaternal: $request->validated('last_name_maternal'),
                phone: $request->validated('phone'),
                occupation: $request->validated('occupation'),
            ));

            return ApiResponse::success((new TutorDetailResource($tutor))->resolve());
        } catch (TutorNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Link a tutor to a student.
     *
     * POST /tutors/{tutorUuid}/students/{studentUuid}
     *
     * Creates an active link in student_tutors. Sends a magic link to the student
     * if this is their first active tutor link and they have not yet verified their email.
     *
     * Responds 200 on success.
     * Responds 404 when the tutor or student UUID does not exist.
     * Responds 409 when the specific tutor+student link already exists and is active.
     * Responds 403 when the authenticated user lacks 'user.create'.
     */
    public function linkStudent(
        string $tutorUuid,
        string $studentUuid,
        Request $request,
        LinkTutorToStudentUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_CREATE->value);

        $tenantSlug = TenantModel::find($tenant->tenantId)->slug ?? '';

        try {
            $useCase->execute(new LinkTutorToStudentInput(
                tutorUserUuid: $tutorUuid,
                studentUserUuid: $studentUuid,
                relationship: $request->input('relationship'),
                tenantSlug: $tenantSlug,
            ));

            return ApiResponse::success(null, 'Tutor linked to student successfully.');
        } catch (\Throwable $e) {
            return match (true) {
                $e instanceof TutorNotFoundException => ApiResponse::notFound($e->getMessage()),
                $e instanceof StudentAlreadyLinkedToTutorException => ApiResponse::conflict($e->getMessage()),
                $e instanceof \RuntimeException && str_contains($e->getMessage(), 'Student user not found') => ApiResponse::notFound('Student not found.'),
                default => throw $e,
            };
        }
    }
}
