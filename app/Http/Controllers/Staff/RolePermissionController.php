<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
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
    public function __construct(
        private readonly AssignPermissionToRoleUseCase $assignPermissionUseCase,
        private readonly RevokePermissionFromRoleUseCase $revokePermissionUseCase,
    ) {}

    /**
     * POST /staff/roles/{uuid}/permissions — Assign a permission to a staff role.
     */
    public function store(
        AssignPermissionRequest $request,
        string $uuid,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $this->assignPermissionUseCase->execute(new AssignPermissionToRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                roleUuid: $uuid,
                permissionUuid: $request->validated('permission_uuid'),
                schoolId: null,
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
     * DELETE /staff/roles/{uuid}/permissions/{permission_uuid} — Revoke a permission from a staff role.
     */
    public function destroy(
        Request $request,
        string $uuid,
        string $permission_uuid,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $this->revokePermissionUseCase->execute(new RevokePermissionFromRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                roleUuid: $uuid,
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
