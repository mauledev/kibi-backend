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
use App\Http\Response\ApiResponse;
use App\Modules\User\Application\UseCases\GetUser\GetUserInput;
use App\Modules\User\Application\UseCases\GetUser\GetUserUseCase;
use App\Modules\User\Application\UseCases\ListUsers\ListUsersInput;
use App\Modules\User\Application\UseCases\ListUsers\ListUsersUseCase;
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
                isOwner: $isOwner,
                accessibleSchoolIds: $isOwner ? [] : $actor->accessibleSchoolIds(),
                requestedSchoolId: $requestedSchoolId,
                perPage: (int) ($request->validated('per_page') ?? 20),
                page: (int) ($request->validated('page') ?? 1),
            ));
        } catch (SchoolAccessDeniedException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }

        $items = UserListResource::collection($result['items'])->resolve();

        $pagination = [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ];

        return ApiResponse::paginated($items, $pagination);
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

            return ApiResponse::success((new UserDetailResource($user))->resolve());

        } catch (UserNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Create a user (not yet implemented).
     * POST /users
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        return ApiResponse::error('Not implemented', 501);
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
