<?php

namespace App\Http\Controllers\Schools;

use App\Common\School\SchoolContext;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Http\Controller;
use App\Http\Requests\Roles\AssignPermissionRequest;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleInput;
use App\Modules\Roles\Application\UseCases\AssignPermissionToRole\AssignPermissionToRoleUseCase;
use App\Modules\Roles\Application\UseCases\RevokePermissionFromRole\RevokePermissionFromRoleInput;
use App\Modules\Roles\Application\UseCases\RevokePermissionFromRole\RevokePermissionFromRoleUseCase;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * POST /schools/{uuid}/roles/{role_uuid}/permissions — Assign a permission to a school role.
     *
     * Reuses the tenant-scoped repository (default binding) since role permissions
     * are not school-specific — they apply uniformly across all schools the role is in.
     */
    public function store(
        AssignPermissionRequest $request,
        string $uuid,
        string $role_uuid,
        AssignPermissionToRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        $schoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        try {
            $useCase->execute(new AssignPermissionToRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                roleUuid: $role_uuid,
                permissionUuid: $request->validated('permission_uuid'),
                schoolId: $schoolId,
            ));

            return ApiResponse::success(null, 'Permission assigned to role');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * DELETE /schools/{uuid}/roles/{role_uuid}/permissions/{permission_uuid} — Revoke a permission from a school role.
     */
    public function destroy(
        Request $request,
        string $uuid,
        string $role_uuid,
        string $permission_uuid,
        RevokePermissionFromRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new RevokePermissionFromRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                roleUuid: $role_uuid,
                permissionUuid: $permission_uuid,
            ));

            return ApiResponse::success(null, 'Permission revoked from role');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }
}
