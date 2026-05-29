<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Requests\Staff\CreateTenantRequest;
use App\Http\Resources\Staff\TenantResource;
use App\Http\Response\ApiResponse;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantInput;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantUseCase;
use App\Modules\Tenant\Domain\Exceptions\EmailAlreadyTakenException;
use App\Modules\Tenant\Domain\Exceptions\TenantSlugAlreadyExistsException;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    /**
     * Create a new tenant with its owner user.
     * POST /staff/tenants
     *
     * Responds 201 with the created tenant and embedded owner.
     * Responds 409 when the slug or email is already taken.
     */
    public function store(CreateTenantRequest $request, CreateTenantUseCase $useCase): JsonResponse
    {
        try {
            $tenant = $useCase->execute(new CreateTenantInput(
                tenantName: $request->validated('tenant_name'),
                tenantSlug: $request->validated('tenant_slug'),
                ownerEmail: $request->validated('owner_email'),
                ownerFirstName: $request->validated('owner_first_name'),
                ownerLastNamePaternal: $request->validated('owner_last_name_paternal'),
                ownerLastNameMaternal: $request->validated('owner_last_name_maternal'),
            ));

            return ApiResponse::created(new TenantResource($tenant));

        } catch (TenantSlugAlreadyExistsException $e) {
            return ApiResponse::conflict($e->getMessage(), ['tenant_slug' => [$e->getMessage()]]);

        } catch (EmailAlreadyTakenException $e) {
            return ApiResponse::conflict($e->getMessage(), ['owner_email' => [$e->getMessage()]]);
        }
    }
}
