<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controller;
use App\Http\Requests\Staff\CreateTenantRequest;
use App\Http\Requests\Staff\UpdateTenantRequest;
use App\Http\Resources\Staff\TenantListResource;
use App\Http\Resources\Staff\TenantResource;
use App\Http\Response\ApiResponse;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantInput;
use App\Modules\Tenant\Application\UseCases\CreateTenant\CreateTenantUseCase;
use App\Modules\Tenant\Application\UseCases\DeleteTenant\DeleteTenantUseCase;
use App\Modules\Tenant\Application\UseCases\GetTenant\GetTenantUseCase;
use App\Modules\Tenant\Application\UseCases\ListTenants\ListTenantsUseCase;
use App\Modules\Tenant\Application\UseCases\UpdateTenant\UpdateTenantInput;
use App\Modules\Tenant\Application\UseCases\UpdateTenant\UpdateTenantUseCase;
use App\Modules\Tenant\Domain\Exceptions\EmailAlreadyTakenException;
use App\Modules\Tenant\Domain\Exceptions\TenantNotFoundException;
use App\Modules\Tenant\Domain\Exceptions\TenantSlugAlreadyExistsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * List all tenants with pagination.
     * GET /staff/tenants
     *
     * Responds 200 with a paginated list of tenants and their owners.
     */
    public function index(Request $request, ListTenantsUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute((int) $request->query('page', 1));

        $items = array_map(
            fn ($t) => (new TenantListResource($t))->resolve(),
            $result['items']
        );

        $pagination = [
            'total' => $result['total'],
            'per_page' => $result['per_page'],
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
        ];

        return ApiResponse::paginated($items, $pagination);
    }

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

    /**
     * Get a single tenant by UUID with its owner.
     * GET /staff/tenants/{uuid}
     *
     * Responds 200 with the tenant and embedded owner.
     * Responds 404 when the tenant is not found.
     */
    public function show(string $uuid, GetTenantUseCase $useCase): JsonResponse
    {
        try {
            $tenant = $useCase->execute($uuid);

            return ApiResponse::success(new TenantResource($tenant));

        } catch (TenantNotFoundException) {
            return ApiResponse::notFound();
        }
    }

    /**
     * Update a tenant's mutable fields.
     * PUT /staff/tenants/{uuid}
     *
     * Responds 200 with the updated tenant.
     * Responds 404 when the tenant is not found.
     * Responds 409 when the new slug is already taken by another tenant.
     */
    public function update(string $uuid, UpdateTenantRequest $request, UpdateTenantUseCase $useCase): JsonResponse
    {
        try {
            $tenant = $useCase->execute(new UpdateTenantInput(
                uuid: $uuid,
                name: $request->validated('name'),
                slug: $request->validated('slug'),
                status: $request->validated('status'),
            ));

            return ApiResponse::success(new TenantResource($tenant));

        } catch (TenantNotFoundException) {
            return ApiResponse::notFound();

        } catch (TenantSlugAlreadyExistsException $e) {
            return ApiResponse::conflict($e->getMessage(), ['slug' => [$e->getMessage()]]);
        }
    }

    /**
     * Soft-delete a tenant.
     * DELETE /staff/tenants/{uuid}
     *
     * Responds 200 with a success message.
     * Responds 404 when the tenant is not found.
     */
    public function destroy(string $uuid, DeleteTenantUseCase $useCase): JsonResponse
    {
        try {
            $useCase->execute($uuid);

            return ApiResponse::success(null, 'Tenant eliminado exitosamente');

        } catch (TenantNotFoundException) {
            return ApiResponse::notFound();
        }
    }
}
