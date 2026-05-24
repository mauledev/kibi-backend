<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Http\Response\ApiResponse;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\UseCases\Logout\LogoutUseCase;
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

            return ApiResponse::success(new LoginResource($output), 'Login exitoso');

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

            return ApiResponse::success(new LoginResource($output), 'Login exitoso');

        } catch (InvalidCredentialsException $e) {
            return ApiResponse::unauthorized($e->getMessage());
        }
    }

    /**
     * Logout — shared by staff and tenant routes.
     * POST /auth/logout | POST /staff/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenId = (int) $request->user()->currentAccessToken()->id;

        $this->logoutUseCase->execute($tokenId);

        return ApiResponse::success(null, 'Sesión cerrada');
    }
}
