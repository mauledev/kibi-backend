<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleInput;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleUseCase;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesInput;
use App\Modules\Roles\Application\UseCases\ListRoles\ListRolesUseCase;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly ListRolesUseCase $listRolesUseCase,
        private readonly GetRoleUseCase $getRoleUseCase,
    ) {}

    /**
     * GET /staff/roles — List all staff (system) roles.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('role.view');

        $roles = $this->listRolesUseCase->execute(new ListRolesInput);

        return ApiResponse::success(RoleResource::collection($roles));
    }

    /**
     * GET /staff/roles/{uuid} — Get a single staff role with its permissions.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('role.view');

        try {
            $role = $this->getRoleUseCase->execute(new GetRoleInput($uuid));

            return ApiResponse::success(new RoleResource($role));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
