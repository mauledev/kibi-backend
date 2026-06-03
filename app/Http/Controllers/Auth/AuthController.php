<?php

namespace App\Http\Controllers\Auth;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OAuthRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Resources\Auth\MeResource;
use App\Http\Response\ApiResponse;
use App\Models\User;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LogoutInput;
use App\Modules\Auth\Application\DTOs\OAuthLoginInput;
use App\Modules\Auth\Application\UseCases\ActivateAccount\ActivateAccountInput;
use App\Modules\Auth\Application\UseCases\ActivateAccount\ActivateAccountUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetMeUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetStaffMeUseCase;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\Logout\LogoutUseCase;
use App\Modules\Auth\Application\UseCases\OAuthLogin\OAuthLoginUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Tenant\Application\UseCases\GetTenantInfo\GetTenantInfoUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LogoutUseCase $logoutUseCase,
    ) {}

    /**
     * Tenant login — requires TenantMiddleware upstream.
     * POST /auth/login
     */
    public function login(LoginRequest $request, LoginUseCase $useCase, TenantContext $tenantContext): JsonResponse
    {
        try {
            $output = $useCase->execute(new LoginInput(
                email: $request->validated('email'),
                password: $request->validated('password'),
                tenantId: $tenantContext->tenantId,
            ));

            return ApiResponse::success(new LoginResource($output), 'Login successful');

        } catch (InvalidCredentialsException $e) {
            return ApiResponse::unauthorized($e->getMessage());
        }
    }

    /**
     * Staff login — no TenantMiddleware, only for app.kibi.com.
     * POST /staff/auth/login
     */
    public function staffLogin(LoginRequest $request, StaffLoginUseCase $useCase): JsonResponse
    {
        try {
            $output = $useCase->execute(new LoginInput(
                email: $request->validated('email'),
                password: $request->validated('password'),
                // Staff does not belong to a tenant: tenantId stays null.
            ));

            return ApiResponse::success(new LoginResource($output), 'Login successful');

        } catch (InvalidCredentialsException $e) {
            return ApiResponse::unauthorized($e->getMessage());
        }
    }

    /**
     * OAuth login — authenticates or registers a user via a provider access token.
     * POST /auth/oauth/{provider}
     *
     * The {provider} route parameter is merged into the request by OAuthRequest::prepareForValidation()
     * before Laravel runs validation, so both access_token and provider are validated together.
     */
    public function oauthLogin(OAuthRequest $request, OAuthLoginUseCase $useCase): JsonResponse
    {
        try {
            $output = $useCase->execute(new OAuthLoginInput(
                provider: $request->validated('provider'),
                accessToken: $request->validated('access_token'),
            ));

            return ApiResponse::success(new LoginResource($output), 'Login successful');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 503);
        }
    }

    /**
     * GET /auth/me — tenant
     */
    public function me(Request $request, GetMeUseCase $useCase): JsonResponse
    {
        $output = $useCase->execute($request->user()->id);

        return ApiResponse::success(new MeResource($output));
    }

    /**
     * GET /staff/auth/me — staff
     */
    public function staffMe(Request $request, GetStaffMeUseCase $useCase): JsonResponse
    {
        $output = $useCase->execute($request->user()->id);

        return ApiResponse::success(new MeResource($output));
    }

    /**
     * Activate an owner account via a signed URL.
     * POST /auth/activate?user={uuid}&expires={timestamp}&signature={hmac}
     *
     * The frontend SPA receives the activation link by email, extracts the query
     * params and passes them to this endpoint. The signature is validated here
     * before delegating to the UseCase.
     *
     * Responds 200 with a login token on success.
     * Responds 422 when the signature is invalid, expired, or the account is already active.
     * Responds 404 when the user UUID does not match any pending user.
     */
    public function activate(ActivateAccountRequest $request, ActivateAccountUseCase $useCase): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('Invalid or expired activation link', 422);
        }

        try {
            $output = $useCase->execute(new ActivateAccountInput(
                userUuid: (string) $request->query('user'),
                password: $request->validated('password'),
            ));

            return ApiResponse::success(new LoginResource($output), 'Account activated successfully');

        } catch (UserNotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * Return basic public information for a tenant identified by the X-Tenant-Slug header.
     * GET /api/auth/tenant-info — public, no authentication required.
     *
     * Returns slug and name for any tenant regardless of its status (active or pending).
     * Responds 400 when the header is absent or blank.
     * Responds 404 when no tenant matches the slug.
     */
    public function tenantInfo(Request $request, GetTenantInfoUseCase $useCase): JsonResponse
    {
        $slug = $request->header('X-Tenant-Slug', '');

        if (empty($slug)) {
            return ApiResponse::error('Missing X-Tenant-Slug header', 400);
        }

        $tenant = $useCase->execute($slug);

        if ($tenant === null) {
            return ApiResponse::notFound('Tenant not found');
        }

        return ApiResponse::success([
            'slug' => $tenant->getSlug(),
            'name' => $tenant->getName(),
        ]);
    }

    /**
     * Logout — shared by staff and tenant routes.
     * POST /auth/logout | POST /staff/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->logoutUseCase->execute(new LogoutInput(
            tokenId: (int) $user->currentAccessToken()->id,
            userId: $user->id,
            tenantId: $user->tenant_id,
        ));

        return ApiResponse::success(null, 'Logged out successfully');
    }
}
