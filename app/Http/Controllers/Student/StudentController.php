<?php

namespace App\Http\Controllers\Student;

use App\Common\School\SchoolContext;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentDetailResource;
use App\Http\Resources\Student\StudentListResource;
use App\Http\Response\ApiResponse;
use App\Models\Group;
use App\Models\School;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Student\Application\UseCases\CreateStudent\CreateStudentInput;
use App\Modules\Student\Application\UseCases\CreateStudent\CreateStudentUseCase;
use App\Modules\Student\Application\UseCases\GetStudent\GetStudentInput;
use App\Modules\Student\Application\UseCases\GetStudent\GetStudentUseCase;
use App\Modules\Student\Application\UseCases\ListStudents\ListStudentsInput;
use App\Modules\Student\Application\UseCases\ListStudents\ListStudentsUseCase;
use App\Modules\Student\Application\UseCases\UpdateStudent\UpdateStudentInput;
use App\Modules\Student\Application\UseCases\UpdateStudent\UpdateStudentUseCase;
use App\Modules\Student\Domain\Exceptions\StudentNotFoundException;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use Illuminate\Http\JsonResponse;

/**
 * StudentController handles CRUD operations for students.
 *
 * index  → ListStudentsUseCase  (GET /students)
 * show   → GetStudentUseCase    (GET /students/{uuid})
 * store  → CreateStudentUseCase (POST /students — requires X-School-Uuid)
 * update → UpdateStudentUseCase (PUT /students/{uuid})
 *
 * Routes use the user's UUID as the public identifier — not the student_profiles uuid.
 * Authorization: all endpoints require 'user.view' (index, show) or 'user.create'/'user.update'.
 */
class StudentController extends Controller
{
    /**
     * List students in the current tenant, with optional filters.
     *
     * GET /students
     * Query params:
     *   q        — free-text search (name / email)
     *   page     — page number (default 1)
     *   per_page — items per page (default 20, max 100)
     *
     * School visibility is authority-driven:
     *   - Owner     → all tenant students; X-School-Uuid optionally narrows to one school.
     *   - Non-owner → only schools they hold an active assignment in.
     *
     * Responds 200 with a paginated list of students.
     */
    public function index(
        ListStudentsRequest $request,
        ListStudentsUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_VIEW->value);

        $actor = $request->user();
        $isOwner = $tenant->ownerId === $actor->id;

        $requestedSchoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        $result = $useCase->execute(new ListStudentsInput(
            search: $request->validated('q') ?: null,
            isOwner: $isOwner,
            accessibleSchoolIds: $isOwner ? [] : $actor->accessibleSchoolIds(),
            requestedSchoolId: $requestedSchoolId,
            perPage: (int) ($request->validated('per_page') ?? 20),
            page: (int) ($request->validated('page') ?? 1),
        ));

        $items = StudentListResource::collection($result['items'])->resolve();

        return ApiResponse::paginated($items, [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ]);
    }

    /**
     * Get a single student by their user UUID.
     *
     * GET /students/{uuid}
     *
     * Responds 200 with the full student detail.
     * Responds 404 when the UUID does not exist within the current tenant.
     * Responds 403 when the authenticated user lacks 'user.view'.
     */
    public function show(string $uuid, GetStudentUseCase $useCase): JsonResponse
    {
        $this->authorize(PermissionSlug::USER_VIEW->value);

        try {
            $student = $useCase->execute(new GetStudentInput(userUuid: $uuid));

            return ApiResponse::success((new StudentDetailResource($student))->resolve());
        } catch (StudentNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Create a new student.
     *
     * POST /students
     * Requires X-School-Uuid header — a student must belong to a school.
     *
     * Creates a pending user (no password), assigns the 'student' role in the given
     * school, and creates the student profile. No activation email is sent.
     *
     * Responds 201 with the created student's detail.
     * Responds 409 when the email is already registered.
     * Responds 403 on a hierarchy / role-exclusion violation.
     * Responds 422 when School context is missing.
     */
    public function store(
        CreateStudentRequest $request,
        CreateStudentUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_CREATE->value);

        // Resolve school UUID — requires SchoolContext (injected by school middleware) and
        // a matching row in the schools table. Both checks collapse into one guard.
        $schoolUuid = app()->bound(SchoolContext::class)
            ? School::where('id', app(SchoolContext::class)->schoolId)->value('uuid')
            : null;

        if ($schoolUuid === null) {
            return ApiResponse::error('School context required. Include X-School-Uuid header with a valid school UUID.', 422);
        }

        $groupId = $request->validated('group_uuid') !== null
            ? Group::where('uuid', $request->validated('group_uuid'))->value('id')
            : null;

        try {
            $student = $useCase->execute(new CreateStudentInput(
                tenantId: $tenant->tenantId,
                actorUuid: $request->user()->uuid,
                actorSlug: $request->user()->resolveActorSlug(),
                schoolUuid: $schoolUuid,
                email: $request->validated('email'),
                firstName: $request->validated('first_name'),
                lastNamePaternal: $request->validated('last_name_paternal'),
                lastNameMaternal: $request->validated('last_name_maternal'),
                phone: $request->validated('phone'),
                birthDate: $request->validated('birth_date'),
                nationalId: $request->validated('national_id'),
                enrollmentNumber: $request->validated('enrollment_number'),
                gender: $request->validated('gender'),
                bloodType: $request->validated('blood_type'),
                groupId: $groupId,
            ));

            return ApiResponse::created((new StudentDetailResource($student))->resolve());
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
     * Update a student's identity and profile fields.
     *
     * PUT /students/{uuid}
     * The {uuid} parameter is the user's UUID.
     *
     * All fields are optional — only provided fields are updated.
     *
     * Responds 200 with the updated student detail.
     * Responds 404 when the student does not exist.
     * Responds 403 when the authenticated user lacks 'user.update'.
     */
    public function update(
        UpdateStudentRequest $request,
        string $uuid,
        UpdateStudentUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::USER_UPDATE->value);

        // Resolve group_uuid to internal group_id when provided
        $groupId = null;
        $groupUuid = $request->validated('group_uuid');
        if ($groupUuid !== null) {
            $groupId = Group::where('uuid', $groupUuid)->value('id');
        }

        try {
            $student = $useCase->execute(new UpdateStudentInput(
                userUuid: $uuid,
                firstName: $request->validated('first_name'),
                lastNamePaternal: $request->validated('last_name_paternal'),
                lastNameMaternal: $request->validated('last_name_maternal'),
                phone: $request->validated('phone'),
                birthDate: $request->validated('birth_date'),
                nationalId: $request->validated('national_id'),
                enrollmentNumber: $request->validated('enrollment_number'),
                gender: $request->validated('gender'),
                bloodType: $request->validated('blood_type'),
                groupId: $groupId,
                actorId: $request->user()->id,
            ));

            return ApiResponse::success((new StudentDetailResource($student))->resolve());
        } catch (StudentNotFoundException) {
            return ApiResponse::notFound();
        }
    }
}
