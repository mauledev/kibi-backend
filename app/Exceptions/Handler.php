<?php

namespace App\Exceptions;

use App\Http\Response\ApiResponse;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserAlreadyExistsException;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Tenant\Domain\Exceptions\EmailAlreadyTakenException;
use App\Modules\Tenant\Domain\Exceptions\TenantSlugAlreadyExistsException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        $this->renderable(function (OwnerRoleAssignmentException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });

        $this->renderable(function (TenantSlugAlreadyExistsException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::conflict(
                    $e->getMessage(),
                    ['tenant_slug' => [$e->getMessage()]]
                );
            }
        });

        $this->renderable(function (EmailAlreadyTakenException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::conflict(
                    $e->getMessage(),
                    ['owner_email' => [$e->getMessage()]]
                );
            }
        });

        // Validation exceptions
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Validation failed',
                    422,
                    $e->errors()
                );
            }
        });

        // Model not found
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound('Resource not found');
            }
        });

        // Authentication
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::unauthorized('Unauthenticated');
            }
        });

        // Authorization
        $this->renderable(function (AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return ApiResponse::forbidden('Access denied');
            }
        });
    }
}
