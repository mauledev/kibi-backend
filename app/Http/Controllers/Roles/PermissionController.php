<?php

namespace App\Http\Controllers\Roles;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Resources\Roles\PermissionResource;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Application\UseCases\ListPermissions\ListPermissionsUseCase;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * GET /permissions — List permissions, optionally scoped by role category.
     * Pass ?role_uuid=X to get only permissions for that role's category.
     * Custom roles (no category) and no role_uuid return all permissions.
     */
    public function index(Request $request, ListPermissionsUseCase $useCase): JsonResponse
    {
        $this->authorize('manage.permissions');

        $request->validate([
            'role_uuid' => ['sometimes', 'nullable', 'string', 'uuid'],
        ]);

        try {
            $permissions = $useCase->execute($request->query('role_uuid'));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }

        return ApiResponse::success(PermissionResource::collection($permissions)->resolve());
    }

    /**
     * GET /schools/{uuid}/permissions?role_uuid=X
     * Return permissions scoped to the role's category, within a school context.
     * The role_uuid query param is required.
     * Returns 404 when the school UUID does not belong to the current tenant.
     */
    public function schoolIndex(
        Request $request,
        string $uuid,
        TenantContext $context,
        ListPermissionsUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        // Validate that the school exists and belongs to the current tenant.
        $school = DB::table('schools')
            ->where('uuid', $uuid)
            ->where('tenant_id', $context->tenantId)
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($school === null) {
            return ApiResponse::notFound('School not found');
        }

        $validated = $request->validate([
            'role_uuid' => ['required', 'string', 'uuid'],
        ]);

        try {
            $permissions = $useCase->execute($validated['role_uuid']);

            return ApiResponse::success(PermissionResource::collection($permissions)->resolve());
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
