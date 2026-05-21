<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use App\Http\Response\ApiResponse;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Auth\Domain\Exceptions\UserAlreadyExistsException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Domain Exceptions
        $this->renderable(function (InvalidCredentialsException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 401);
            }
        });

        $this->renderable(function (UserNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound($e->getMessage());
            }
        });

        $this->renderable(function (UserAlreadyExistsException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::conflict(
                    $e->getMessage(),
                    ['email' => [$e->getMessage()]]
                );
            }
        });

        // Validation exceptions
        $this->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Validación fallida',
                    422,
                    $e->errors()
                );
            }
        });

        // Model not found
        $this->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound('Recurso no encontrado');
            }
        });

        // Authentication
        $this->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::unauthorized('No autenticado');
            }
        });

        // Authorization
        $this->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::forbidden('Acceso denegado');
            }
        });
    }
}
