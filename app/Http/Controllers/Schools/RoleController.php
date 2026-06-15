<?php

namespace App\Http\Controllers\Schools;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Requests\Schools\CreateSchoolRoleRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleInput;
use App\Modules\Roles\Application\UseCases\CreateRole\CreateRoleUseCase;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleInput;
use App\Modules\Roles\Application\UseCases\GetRole\GetRoleUseCase;
use App\Modules\Roles\Application\UseCases\ListRoles\ListSchoolRolesInput;
use App\Modules\Roles\Application\UseCases\ListRoles\ListSchoolRolesUseCase;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleInput;
use App\Modules\Roles\Application\UseCases\UpdateRole\UpdateRoleUseCase;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\CustomRoleLimitExceededException;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /schools/{uuid}/roles — List all roles available in the given school.
     *
     * Returns system school-scoped roles and custom roles linked to this school.
     */
    public function index(
        Request $request,
        string $uuid,
        ListSchoolRolesUseCase $useCase,
        SchoolRepositoryInterface $schoolRepository,
        TenantContext $context,
    ): JsonResponse {
        $this->authorize('role.view');

        $schoolId = $schoolRepository->findIdByUuid($uuid);

        if ($schoolId === null) {
            return ApiResponse::notFound('School not found.');
        }

        $roles = $useCase->execute(new ListSchoolRolesInput(
            schoolId: $schoolId,
            tenantId: $context->tenantId,
        ));

        return ApiResponse::success(RoleResource::collection($roles));
    }

    /**
     * GET /schools/{uuid}/roles/{role_uuid} — Get a single role available in the given school.
     *
     * Validates that the role is school-scoped or is a custom role available in this school.
     */
    public function show(
        Request $request,
        string $uuid,
        string $role_uuid,
        GetRoleUseCase $useCase,
        SchoolRepositoryInterface $schoolRepository,
        TenantContext $context,
        ListSchoolRolesUseCase $listUseCase,
    ): JsonResponse {
        $this->authorize('role.view');

        $schoolId = $schoolRepository->findIdByUuid($uuid);

        if ($schoolId === null) {
            return ApiResponse::notFound('School not found.');
        }

        try {
            $role = $useCase->execute(new GetRoleInput($role_uuid));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }

        // Validate the role is actually available in this school
        $schoolRoles = $listUseCase->execute(new ListSchoolRolesInput(
            schoolId: $schoolId,
            tenantId: $context->tenantId,
        ));

        $isAvailable = false;
        foreach ($schoolRoles as $schoolRole) {
            if ($schoolRole->getUuid() === $role_uuid) {
                $isAvailable = true;
                break;
            }
        }

        if (! $isAvailable) {
            return ApiResponse::notFound('Role not found in this school.');
        }

        return ApiResponse::success((new RoleResource($role)));
    }

    /**
     * POST /schools/{uuid}/roles — Create a custom role scoped to this school.
     */
    public function store(
        CreateSchoolRoleRequest $request,
        string $uuid,
        CreateRoleUseCase $useCase,
        SchoolRepositoryInterface $schoolRepository,
        TenantContext $context,
    ): JsonResponse {
        $this->authorize('roles.custom.create');

        $schoolId = $schoolRepository->findIdByUuid($uuid);

        if ($schoolId === null) {
            return ApiResponse::notFound('School not found.');
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $role = $useCase->execute(new CreateRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                tenantId: $context->tenantId,
                name: $request->validated('name'),
                schoolUuids: [$uuid],
                slug: $request->validated('slug'),
            ));

            return ApiResponse::created((new RoleResource($role)));
        } catch (HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (CustomRoleLimitExceededException $e) {
            return ApiResponse::conflict($e->getMessage());
        }
    }

    /**
     * PUT /schools/{uuid}/roles/{role_uuid} — Update a custom role available in this school.
     */
    public function update(
        UpdateRoleRequest $request,
        string $uuid,
        string $role_uuid,
        UpdateRoleUseCase $useCase,
        SchoolRepositoryInterface $schoolRepository,
        TenantContext $context,
        ListSchoolRolesUseCase $listUseCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        $schoolId = $schoolRepository->findIdByUuid($uuid);

        if ($schoolId === null) {
            return ApiResponse::notFound('School not found.');
        }

        $schoolRoles = $listUseCase->execute(new ListSchoolRolesInput(
            schoolId: $schoolId,
            tenantId: $context->tenantId,
        ));

        $isAvailable = false;
        foreach ($schoolRoles as $schoolRole) {
            if ($schoolRole->getUuid() === $role_uuid) {
                $isAvailable = true;
                break;
            }
        }

        if (! $isAvailable) {
            return ApiResponse::notFound('Role not found in this school.');
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $role = $useCase->execute(new UpdateRoleInput(
                actorUserId: $actor->id,
                actorSlug: $actor->resolveActorSlug(),
                uuid: $role_uuid,
                name: $request->validated('name'),
            ));

            return ApiResponse::success((new RoleResource($role)));
        } catch (RoleNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        } catch (SystemRoleViolationException|HierarchyViolationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }
}
