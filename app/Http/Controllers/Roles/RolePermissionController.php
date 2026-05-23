<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * POST /roles/{public_id}/permissions — Assign a permission to a role.
     */
    public function store(
        AssignPermissionRequest $request,
        string $public_id,
        AssignPermissionToRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new AssignPermissionToRoleInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                actorCanManagePermissions: $actor->hasRole('owner') || $actor->hasPermissionTo('manage.permissions'),
                rolePublicId: $public_id,
                permissionPublicId: $request->validated('permission_public_id'),
            ));

            return ApiResponse::success(null, 'Permission assigned to role');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException|AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * DELETE /roles/{public_id}/permissions/{permission_public_id} — Revoke a permission from a role.
     */
    public function destroy(
        Request $request,
        string $public_id,
        string $permission_public_id,
        RevokePermissionFromRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new RevokePermissionFromRoleInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                actorCanManagePermissions: $actor->hasRole('owner') || $actor->hasPermissionTo('manage.permissions'),
                rolePublicId: $public_id,
                permissionPublicId: $permission_public_id,
            ));

            return ApiResponse::success(null, 'Permission revoked from role');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException|AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }
}
