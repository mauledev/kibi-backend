<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controller;
use App\Http\Response\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserAlreadyExistsException;

/**
 * AuthController
 * Controlador DELGADO - solo maneja HTTP
 * Toda lógica en UseCases
 */
class AuthController extends Controller
{
    public function __construct(
        private LoginUseCase $loginUseCase
    ) {
    }

    /**
     * Login
     * POST /api/auth/login
     */
    public function login(LoginRequest $request)
    {
        try {
            $output = $this->loginUseCase->execute(
                new LoginInput(
                    email: $request->email,
                    password: $request->password
                )
            );

            return ApiResponse::success(
                new UserResource($output),
                'Login exitoso',
                200
            );

        } catch (InvalidCredentialsException $e) {
            return ApiResponse::error(
                $e->getMessage(),
                401
            );
        }
    }

    /**
     * Register
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Aquí iría RegisterUseCase cuando lo crees
            // $output = $this->registerUseCase->execute(...)
            
            return ApiResponse::created(
                null,
                'Usuario registrado exitosamente. Por favor inicia sesión.'
            );

        } catch (UserAlreadyExistsException $e) {
            return ApiResponse::conflict(
                $e->getMessage(),
                ['email' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Logout
     * POST /api/auth/logout
     */
    public function logout()
    {
        auth()->logout();

        return ApiResponse::success(
            null,
            'Sesión cerrada exitosamente'
        );
    }
}
