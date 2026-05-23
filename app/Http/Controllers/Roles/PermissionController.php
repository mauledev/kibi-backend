<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\Http\Controller;
use App\Http\Resources\Roles\PermissionResource;
use App\Http\Response\ApiResponse;
use App\Modules\Roles\Application\UseCases\ListPermissions\ListPermissionsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * GET /permissions — List all system permissions.
     */
    public function index(Request $request, ListPermissionsUseCase $useCase): JsonResponse
    {
        $this->authorize('manage.permissions');

        $permissions = $useCase->execute();

        return ApiResponse::success(PermissionResource::collection($permissions)->resolve());
    }
}
