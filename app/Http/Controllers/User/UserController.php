<?php

namespace App\Http\Controllers\User;

use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserDetailResource;
use App\Http\Resources\User\UserListResource;
use App\Http\Resources\User\UserStatsResource;
use App\Http\Response\ApiResponse;
use App\Models\Tenant as TenantModel;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\User\Application\UseCases\GetUser\GetUserInput;
use App\Modules\User\Application\UseCases\GetUser\GetUserUseCase;
use App\Modules\User\Application\UseCases\GetUserStats\GetUserStatsInput;
use App\Modules\User\Application\UseCases\GetUserStats\GetUserStatsUseCase;
use App\Modules\User\Application\UseCases\InviteUser\InviteUserInput;
use App\Modules\User\Application\UseCases\InviteUser\InviteUserUseCase;
use App\Modules\User\Application\UseCases\ListUsers\ListUsersInput;
use App\Modules\User\Application\UseCases\ListUsers\ListUsersUseCase;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use App\Modules\User\Domain\Exceptions\SchoolAccessDeniedException;
use App\Modules\User\Domain\Exceptions\UserNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * UserController handles read operations for tenant users.
 *
 * index  → ListUsersUseCase  (GET /users)
 * show   → GetUserUseCase    (GET /users/{uuid})
 * store / update / destroy   → stubs (not yet implemented)
 *
 * Authorization: both read endpoints require the 'user.view' permission.
 * The X-School-Uuid header is optional — when present, SchoolContext is bound
 * by SchoolMiddleware and the school filter is applied automatically.
 */
class UserController extends Controller
{
    /**
     * List users belonging to the current tenant, with optional filters.
     *
     * GET /users
     * Query params:
     *   q             — free-text search (name / email)
     *   filter[role]  — role slug(s); string or array
     *   filter[status] — lifecycle status (active | inactive | suspended)
     *   page          — page number (default 1)
     *   per_page      — items per page (default 20, max 100)
     *
     * School visibility is authority-driven (resolved in ListUsersUseCase):
     *   - Owner     → all tenant users; X-School-Uuid optionally narrows to one school.
     *   - Non-owner → only schools they hold an active assignment in. Without the
     *                 header they see all their accessible schools at once; with it,
     *                 the requested school must be within their access or it is 403.
     *
     * Responds 200 with a paginated list of users.
     * Responds 403 when the user lacks 'user.view' or requests a school outside their access.
     */
    public function index(ListUsersRequest $request, ListUsersUseCase $useCase, TenantContext $tenant): JsonResponse
    {
        $this->authorize('user.view');

        $actor = $request->user();

        $isOwner = $tenant->ownerId === $actor->id;

        $requestedSchoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        try {
            $result = $useCase->execute(new ListUsersInput(
                search: $request->validated('q') ?: null,
                roleSlugs: $request->roleSlugs(),
                status: $request->validated('filter.status') ?: null,
                unassigned: $request->wantsUnassigned(),
                isOwner: $isOwner,
                accessibleSchoolIds: $isOwner ? [] : $actor->accessibleSchoolIds(),
                requestedSchoolId: $requestedSchoolId,
                perPage: (int) ($request->validated('per_page') ?? 20),
                page: (int) ($request->validated('page') ?? 1),
            ));
        } catch (SchoolAccessDeniedException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }

        $items = UserListResource::collection($result['items']);

        $pagination = [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ];

        return ApiResponse::paginated($items, $pagination);
    }

    /**
     * Directory stats for the cards (total users, pending invitations).
     *
     * GET /users/stats
     * Accepts the same `filter[role]` scope as the list (the client sends the
     * directory's non-family role slugs). School visibility is authority-driven,
     * identical to index: the X-School-Uuid header narrows to the active school.
     *
     * Responds 200 with `{ total, pending }`.
     * Responds 403 when the user lacks 'user.view' or requests a school outside their access.
     */
    public function stats(ListUsersRequest $request, GetUserStatsUseCase $useCase, TenantContext $tenant): JsonResponse
    {
        $this->authorize('user.view');

        $actor = $request->user();

        $isOwner = $tenant->ownerId === $actor->id;

        $requestedSchoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        try {
            $stats = $useCase->execute(new GetUserStatsInput(
                roleSlugs: $request->roleSlugs(),
                isOwner: $isOwner,
                accessibleSchoolIds: $isOwner ? [] : $actor->accessibleSchoolIds(),
                requestedSchoolId: $requestedSchoolId,
            ));
        } catch (SchoolAccessDeniedException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }

        return ApiResponse::success((new UserStatsResource($stats))->resolve());
    }

    /**
     * Get a single user by UUID.
     *
     * GET /users/{uuid}
     *
     * Responds 200 with the full user detail.
     * Responds 404 when the UUID does not exist within the current tenant.
     * Responds 403 when the authenticated user lacks 'user.view'.
     */
    public function show(string $uuid, GetUserUseCase $useCase): JsonResponse
    {
        $this->authorize('user.view');

        try {
            $user = $useCase->execute(new GetUserInput(uuid: $uuid));

            return ApiResponse::success((new UserDetailResource($user)));

        } catch (UserNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Invite a tenant user.
     * POST /users
     *
     * Creates a pending user (no password) with the given role/school assignments
     * and emails a signed activation (magic link). The invitee sets a password on
     * activation and is logged in — same flow as the owner.
     *
     * Responds 201 with the created user's public fields.
     * Responds 409 when the email is already registered.
     * Responds 403 on a hierarchy / role-exclusion violation.
     */
    public function store(
        CreateUserRequest $request,
        InviteUserUseCase $useCase,
        TenantContext $tenant,
    ): JsonResponse {
        $this->authorize('user.create');

        $actor = $request->user();

        $tenantSlug = TenantModel::find($tenant->tenantId)->slug ?? '';

        /** @var array<int, array{role_uuid: string, school_uuid?: string|null}> $rawAssignments */
        $rawAssignments = $request->validated('assignments');
        $assignments = array_map(
            fn (array $a): array => [
                'roleUuid' => $a['role_uuid'],
                'schoolUuid' => $a['school_uuid'] ?? null,
            ],
            $rawAssignments,
        );

        try {
            $user = $useCase->execute(new InviteUserInput(
                tenantId: $tenant->tenantId,
                tenantSlug: $tenantSlug,
                actorUuid: $actor->uuid,
                actorSlug: $actor->resolveActorSlug(),
                email: $request->validated('email'),
                firstName: $request->validated('first_name'),
                lastNamePaternal: $request->validated('last_name_paternal'),
                lastNameMaternal: $request->validated('last_name_maternal'),
                assignments: $assignments,
            ));

            return ApiResponse::created([
                'uuid' => $user->getUuid(),
                'email' => $user->getEmail(),
                'full_name' => $user->getFullName(),
            ]);
        } catch (EmailAlreadyTakenException $e) {
            return ApiResponse::conflict($e->getMessage(), ['email' => [$e->getMessage()]]);
        } catch (HierarchyViolationException|RoleExclusionException|OwnerRoleAssignmentException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * Update a user (not yet implemented).
     * PUT /users/{uuid}
     */
    public function update(UpdateUserRequest $request, string $uuid): JsonResponse
    {
        return ApiResponse::error('Not implemented', 501);
    }

    /**
     * Delete a user (not yet implemented).
     * DELETE /users/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        return ApiResponse::error('Not implemented', 501);
    }
}
