<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Requests\Roles\AssignRoleToUserRequest;
use App\Http\Requests\Roles\RevokeRoleFromUserRequest;
use App\Http\Resources\Roles\UserRoleAssignmentResource;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserInput;
use App\Modules\Roles\Application\UseCases\RevokeRoleFromUser\RevokeRoleFromUserUseCase;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    /**
     * POST /users/{uuid}/roles — Assign a role to a user.
     */
    public function store(
        AssignRoleToUserRequest $request,
        string $uuid,
        AssignRoleToUserUseCase $useCase,
    ): JsonResponse {
        $this->authorize('role.assign');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $assignment = $useCase->execute(new AssignRoleToUserInput(
                actorUuid: $actor->uuid,
                actorSlug: $this->resolveActorSlug($actor),
                targetUserUuid: $uuid,
                roleUuid: $request->validated('role_uuid'),
                schoolUuid: $request->validated('school_uuid'),
            ));

            return ApiResponse::created((new UserRoleAssignmentResource($assignment))->resolve());
        } catch (UserNotFoundException) {
            return ApiResponse::notFound('User not found');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (HierarchyViolationException|RoleExclusionException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
        // OwnerRoleAssignmentException is handled by the global exception handler → 422
    }

    /**
     * DELETE /users/{uuid}/roles/{role_uuid} — Revoke a role from a user.
     */
    public function destroy(
        RevokeRoleFromUserRequest $request,
        string $uuid,
        string $role_uuid,
        RevokeRoleFromUserUseCase $useCase,
    ): JsonResponse {
        $this->authorize('role.revoke');

        /** @var User $actor */
        $actor = $request->user();

        try {
            $assignment = $useCase->execute(new RevokeRoleFromUserInput(
                actorUuid: $actor->uuid,
                actorSlug: $this->resolveActorSlug($actor),
                targetUserUuid: $uuid,
                roleUuid: $role_uuid,
                schoolUuid: $request->validated('school_uuid'),
            ));

            return ApiResponse::success(
                (new UserRoleAssignmentResource($assignment))->resolve(),
                'Role revoked from user'
            );
        } catch (UserNotFoundException) {
            return ApiResponse::notFound('User not found');
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (AssignmentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * Resolve the actor's primary slug for hierarchy validation.
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
