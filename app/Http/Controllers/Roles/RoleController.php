<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Requests\Roles\CreateRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleInput;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleUseCase;
use App\Modules\Roles\Application\UseCases\DeleteRole\DeleteRoleInput;
use App\Modules\Roles\Application\UseCases\DeleteRole\DeleteRoleUseCase;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleInput;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleUseCase;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesInput;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesUseCase;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleInput;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleUseCase;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /roles — List all roles for the current tenant.
     */
    public function index(Request $request, ListRolesUseCase $useCase): JsonResponse
    {
        $this->authorize('role.view');

        $roles = $useCase->execute(new ListRolesInput);

        return ApiResponse::success(RoleResource::collection($roles)->resolve());
    }

    /**
     * POST /roles — Create a new role.
     */
    public function store(CreateRoleRequest $request, CreateRoleUseCase $useCase): JsonResponse
    {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $role = $useCase->execute(new CreateRoleInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                tenantId: $actor->tenant_id,
                name: $request->validated('name'),
                slug: $request->validated('slug'),
                hierarchyLevel: (int) $request->validated('hierarchy_level'),
            ));

            return ApiResponse::created((new RoleResource($role))->resolve());
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * GET /roles/{public_id} — Get a single role with its permissions.
     */
    public function show(Request $request, string $public_id, GetRoleUseCase $useCase): JsonResponse
    {
        $this->authorize('role.view');

        try {
            $role = $useCase->execute(new GetRoleInput($public_id));

            return ApiResponse::success((new RoleResource($role))->resolve());
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * PUT /roles/{public_id} — Update a role's name.
     */
    public function update(UpdateRoleRequest $request, string $public_id, UpdateRoleUseCase $useCase): JsonResponse
    {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $role = $useCase->execute(new UpdateRoleInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                publicId: $public_id,
                name: $request->validated('name'),
            ));

            return ApiResponse::success((new RoleResource($role))->resolve());
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * DELETE /roles/{public_id} — Soft-delete a role.
     */
    public function destroy(Request $request, string $public_id, DeleteRoleUseCase $useCase): JsonResponse
    {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new DeleteRoleInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                publicId: $public_id,
            ));

            return ApiResponse::success(null, 'Role deleted successfully');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }
}
