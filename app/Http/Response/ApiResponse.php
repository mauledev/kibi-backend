<?php

namespace App\Http\Response;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse Helper
 * Standardizes all API responses.
 */
class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operación exitosa',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => self::getMeta(),
        ], $status);
    }

    public static function created(
        mixed $data,
        string $message = 'Recurso creado exitosamente'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /** @param array<string, mixed>|null $errors */
    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data ?? null,
            'errors' => $errors ?? null,
            'meta' => self::getMeta(),
        ], $status);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'No autenticado'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): JsonResponse
    {
        return self::error($message, 403);
    }

    /** @param array<string, mixed> $errors */
    public static function conflict(string $message, array $errors = []): JsonResponse
    {
        return self::error($message, 409, $errors);
    }

    /** @param array<string, mixed> $pagination */
    public static function paginated(mixed $data, array $pagination): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Operación exitosa',
            'data' => $data,
            'errors' => null,
            'meta' => array_merge(self::getMeta(), ['pagination' => $pagination]),
        ], 200);
    }

    /** @return array<string, mixed> */
    private static function getMeta(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'path' => request()->getPathInfo(),
            'request_id' => request()->header('X-Request-ID') ?? 'req_'.uniqid(),
        ];
    }
}
