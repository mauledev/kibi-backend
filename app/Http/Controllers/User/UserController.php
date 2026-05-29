<?php

namespace App\Http\Controllers\User;

use App\Http\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserCreateResource;
use App\Http\Resources\User\UserDetailResource;
use App\Http\Resources\User\UserListResource;
use App\Http\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserController
 * CRUD de usuarios
 * En versión real, cada método llamaría a su correspondiente UseCase
 */
class UserController extends Controller
{
    /**
     * Listar usuarios
     * GET /api/users
     * Parámetros: page, per_page, filter[role], filter[status]
     */
    public function index(Request $request): JsonResponse
    {
        // Aquí iría ListUsersUseCase
        // $output = $this->listUsersUseCase->execute(...)

        return ApiResponse::success(
            UserListResource::collection([]),
            'Usuarios obtenidos correctamente'
        );
    }

    /**
     * Obtener usuario por ID
     * GET /api/users/{id}
     */
    public function show(string $id): JsonResponse
    {
        // Aquí iría GetUserUseCase
        // $output = $this->getUserUseCase->execute($id)

        return ApiResponse::success(
            new UserDetailResource([]),
            'Usuario obtenido correctamente'
        );
    }

    /**
     * Crear usuario
     * POST /api/users
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            // Aquí iría CreateUserUseCase
            // $output = $this->createUserUseCase->execute(...)

            return ApiResponse::created(
                new UserCreateResource([]),
                'Usuario creado exitosamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al crear usuario: '.$e->getMessage(),
                400
            );
        }
    }

    /**
     * Actualizar usuario
     * PATCH /api/users/{id}
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        try {
            // Aquí iría UpdateUserUseCase
            // $output = $this->updateUserUseCase->execute(...)

            return ApiResponse::success(
                new UserDetailResource([]),
                'Usuario actualizado correctamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al actualizar usuario: '.$e->getMessage(),
                400
            );
        }
    }

    /**
     * Eliminar usuario
     * DELETE /api/users/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // Aquí iría DeleteUserUseCase
            // $output = $this->deleteUserUseCase->execute($id)

            return ApiResponse::success(
                null,
                'Usuario eliminado correctamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al eliminar usuario: '.$e->getMessage(),
                400
            );
        }
    }
}
