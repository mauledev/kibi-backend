<?php

namespace App\Http\Controllers\Roles;

use App\Common\Tenant\TenantContext;
use App\Modules\Roles\Domain\Enums\PermissionSlug;
use App\Http\Controller;
use App\Http\Requests\Roles\UpdateCustomRoleLimitRequest;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitInput;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitUseCase;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CustomRoleLimitController extends Controller
{
    /**
     * PUT /tenant/custom-roles-limit
     * Set the maximum number of custom roles for the tenant. Owner only.
     */
    public function update(
        UpdateCustomRoleLimitRequest $request,
        TenantContext $context,
        ConfigureCustomRoleLimitUseCase $useCase,
    ): JsonResponse {
        $this->authorize(PermissionSlug::MANAGE_PERMISSIONS->value);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new ConfigureCustomRoleLimitInput(
                actorUserId: $actor->id,
                tenantId: $context->tenantId,
                limit: (int) $request->validated('limit'),
            ));

            return ApiResponse::success(null, 'Custom roles limit updated');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
