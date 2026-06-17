<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Requests\Roles\ListPermissionsRequest;
use App\Http\Requests\Roles\ListSchoolPermissionsRequest;
use App\Http\Resources\Roles\PermissionResource;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Application\UseCases\ListPermissions\ListPermissionsUseCase;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolInput;
use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolUseCase;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * GET /permissions — List permissions, optionally scoped by role category.
     * Pass ?role_uuid=X to get only permissions for that role's category.
     * Custom roles (no category) and no role_uuid return all permissions.
     */
    public function index(ListPermissionsRequest $request, ListPermissionsUseCase $useCase): JsonResponse
    {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        try {
            $permissions = $useCase->execute($request->validated('role_uuid'));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }

        return ApiResponse::success(PermissionResource::collection($permissions));
    }

    /**
     * GET /schools/{uuid}/permissions?role_uuid=X
     * Return permissions scoped to the role's category, within a school context.
     * The role_uuid query param is required.
     * Returns 404 when the school UUID does not belong to the current tenant.
     */
    public function schoolIndex(
        ListSchoolPermissionsRequest $request,
        string $uuid,
        GetSchoolUseCase $getSchool,
        ListPermissionsUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        try {
            $getSchool->execute(new GetSchoolInput($uuid));
        } catch (SchoolNotFoundException) {
            return ApiResponse::notFound('School not found');
        }

        try {
            $permissions = $useCase->execute($request->validated('role_uuid'));

            return ApiResponse::success(PermissionResource::collection($permissions));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
