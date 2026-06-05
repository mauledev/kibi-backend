<?php

namespace App\Http\Controllers\Roles;

use App\Common\School\SchoolContext;
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
     * POST /roles/{uuid}/permissions — Assign a permission to a role.
     */
    public function store(
        AssignPermissionRequest $request,
        string $uuid,
        AssignPermissionToRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        $schoolId = app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;

        $actorSlug = $this->resolveActorSlug($actor);

        try {
            $useCase->execute(new AssignPermissionToRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actorSlug,
                roleUuid: $uuid,
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
     * DELETE /roles/{uuid}/permissions/{permission_uuid} — Revoke a permission from a role.
     */
    public function destroy(
        Request $request,
        string $uuid,
        string $permission_uuid,
        RevokePermissionFromRoleUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        /** @var User $actor */
        $actor = $request->user();

        $actorSlug = $this->resolveActorSlug($actor);

        try {
            $useCase->execute(new RevokePermissionFromRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actorSlug,
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

    /**
     * Resolve the actor's primary slug for hierarchy validation.
     * Checks in order: owner, gestor_escuelas, director.
     */
    private function resolveActorSlug(User $actor): string
    {
        foreach (['owner', 'gestor_escuelas', 'director'] as $slug) {
            if ($actor->hasRole($slug)) {
                return $slug;
            }
        }

        return 'unknown';
    }
}
