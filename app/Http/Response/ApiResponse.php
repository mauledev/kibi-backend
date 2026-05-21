<?php

namespace App\Http\Response;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse Helper
 * Estandariza todas las respuestas API
 */
class ApiResponse
{
    public static function success(
        $data = null,
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
        $data,
        string $message = 'Recurso creado exitosamente'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
        ?array $data = null
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

    public static function conflict(string $message, array $errors = []): JsonResponse
    {
        return self::error($message, 409, $errors);
    }

    private static function getMeta(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'path' => request()->getPathInfo(),
            'request_id' => request()->header('X-Request-ID') ?? 'req_' . uniqid(),
        ];
    }
}
