<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Requests\Roles\AssignRoleToUserRequest;
use App\Http\Resources\Roles\UserRoleAssignmentResource;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Models\User as UserModel;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserInput;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserUseCase;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * POST /users/{public_id}/roles — Assign a role to a user.
     */
    public function store(
        AssignRoleToUserRequest $request,
        string $public_id,
        AssignRoleToUserUseCase $useCase,
    ): JsonResponse {
        $this->authorize('role.assign');

        $targetUser = UserModel::where('public_id', $public_id)->first();

        if ($targetUser === null) {
            return ApiResponse::notFound('User not found');
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $assignment = $useCase->execute(new AssignRoleToUserInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                targetUserId: $targetUser->id,
                rolePublicId: $request->validated('role_public_id'),
                schoolId: $request->validated('school_id') !== null
                    ? (int) $request->validated('school_id')
                    : null,
            ));

            return ApiResponse::created((new UserRoleAssignmentResource($assignment))->resolve());
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * DELETE /users/{public_id}/roles/{role_public_id} — Revoke a role from a user.
     */
    public function destroy(
        Request $request,
        string $public_id,
        string $role_public_id,
        RevokeRoleFromUserUseCase $useCase,
    ): JsonResponse {
        $this->authorize('role.revoke');

        $targetUser = UserModel::where('public_id', $public_id)->first();

        if ($targetUser === null) {
            return ApiResponse::notFound('User not found');
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $assignment = $useCase->execute(new RevokeRoleFromUserInput(
                actorUserId: $actor->id,
                actorHierarchyLevel: $actor->lowestHierarchyLevel(),
                targetUserId: $targetUser->id,
                rolePublicId: $role_public_id,
                schoolId: null,
            ));

            return ApiResponse::success(
                (new UserRoleAssignmentResource($assignment))->resolve(),
                'Role revoked from user'
            );
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (AssignmentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
