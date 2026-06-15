<?php

namespace App\Http\Controllers\Me;

use App\Http\Controller;
use App\Http\Resources\Schools\UserSchoolResource;
use App\Http\Response\ApiResponse;
use App\Modules\Schools\Application\UseCases\GetUserSchools\GetUserSchoolsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Schools the authenticated user can operate in.
 *
 * Consumed by the client `SchoolGate` / school switcher to decide the
 * pre-dashboard flow (no schools / one school / pick a school).
 */
class MeSchoolsController extends Controller
{
    /**
     * GET /me/schools
     *
     * Returns only the active schools where the user holds an active role
     * assignment (no role in a school = no access). Compact shape:
     * { id (uuid), slug, name, logo_url }.
     */
    public function show(Request $request, GetUserSchoolsUseCase $useCase): JsonResponse
    {
        $schools = $useCase->execute($request->user()->accessibleSchoolIds());

        return ApiResponse::success(UserSchoolResource::collection($schools));
    }
}
