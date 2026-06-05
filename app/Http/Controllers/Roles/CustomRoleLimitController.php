<?php

namespace App\Http\Controllers\Roles;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitInput;
use App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit\ConfigureCustomRoleLimitUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomRoleLimitController extends Controller
{
    /**
     * PUT /tenant/custom-roles-limit
     * Set the maximum number of custom roles for the tenant. Owner only.
     */
    public function update(
        Request $request,
        TenantContext $context,
        ConfigureCustomRoleLimitUseCase $useCase,
    ): JsonResponse {
        $this->authorize('manage.permissions');

        $validated = $request->validate([
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $useCase->execute(new ConfigureCustomRoleLimitInput(
                actorUserId: $actor->id,
                tenantId: $context->tenantId,
                limit: (int) $validated['limit'],
            ));

            return ApiResponse::success(null, 'Custom roles limit updated');
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
