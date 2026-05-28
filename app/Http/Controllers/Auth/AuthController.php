<?php

namespace App\Http\Controllers\Auth;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OAuthRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Response\ApiResponse;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\OAuthLoginInput;
use App\Http\Resources\Auth\MeResource;
use App\Modules\Auth\Application\UseCases\GetMe\GetMeUseCase;
use App\Modules\Auth\Application\UseCases\GetMe\GetStaffMeUseCase;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\Logout\LogoutUseCase;
use App\Modules\Auth\Application\UseCases\OAuthLogin\OAuthLoginUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
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
    public function login(LoginRequest $request, LoginUseCase $useCase): JsonResponse
    {
        try {
            $output = $useCase->execute(new LoginInput(
                email: $request->validated('email'),
                password: $request->validated('password'),
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
    public function oauthLogin(OAuthRequest $request, OAuthLoginUseCase $useCase, TenantContext $context): JsonResponse
    {
        try {
            $output = $useCase->execute(new OAuthLoginInput(
                provider: $request->validated('provider'),
                accessToken: $request->validated('access_token'),
                tenantId: $context->tenantId,
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
     * Logout — shared by staff and tenant routes.
     * POST /auth/logout | POST /staff/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenId = (int) $request->user()->currentAccessToken()->id;

        $this->logoutUseCase->execute($tokenId);

        return ApiResponse::success(null, 'Logged out successfully');
    }
}
