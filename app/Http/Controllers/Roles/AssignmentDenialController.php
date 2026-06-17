<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Requests\Roles\DenyPermissionRequest;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment\DenyPermissionFromAssignmentInput;
use App\Modules\Roles\Application\UseCases\DenyPermissionFromAssignment\DenyPermissionFromAssignmentUseCase;
use App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment\RestorePermissionToAssignmentInput;
use App\Modules\Roles\Application\UseCases\RestorePermissionToAssignment\RestorePermissionToAssignmentUseCase;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Modules\Roles\Domain\Exceptions\AssignmentNotFoundException;
use App\Modules\Roles\Domain\Exceptions\PermissionNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentDenialController extends Controller
{
    /**
     * POST /users/{uuid}/assignments/{assignment_uuid}/denials
     * Add a permission denial to a specific assignment.
     */
    public function store(
        DenyPermissionRequest $request,
        string $uuid,
        string $assignment_uuid,
        DenyPermissionFromAssignmentUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $created = $useCase->execute(new DenyPermissionFromAssignmentInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                assignmentUuid: $assignment_uuid,
                permissionUuid: $request->validated('permission_uuid'),
            ));

            return $created
                ? ApiResponse::created(null, 'Permission denial added')
                : ApiResponse::success(null, 'Permission denial already exists');
        } catch (AssignmentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * DELETE /users/{uuid}/assignments/{assignment_uuid}/denials/{permission_uuid}
     * Remove a permission denial from a specific assignment.
     */
    public function destroy(
        Request $request,
        string $uuid,
        string $assignment_uuid,
        string $permission_uuid,
        RestorePermissionToAssignmentUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new RestorePermissionToAssignmentInput(
                actorUserId: $actor->id,
                assignmentUuid: $assignment_uuid,
                permissionUuid: $permission_uuid,
            ));

            return ApiResponse::success(null, 'Permission denial removed');
        } catch (AssignmentNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (PermissionNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }
}
