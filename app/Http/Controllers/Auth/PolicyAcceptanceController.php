<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controller;
use App\Http\Response\ApiResponse;
use App\Modules\Auth\Application\UseCases\AcceptPolicy\AcceptPolicyUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyAcceptanceController extends Controller
{
    /**
     * Record the current user's acceptance of the Responsible Use Policy.
     * POST /staff/auth/policy/accept
     *
     * Lifts the `policy.accepted` gate for the user. Idempotent (200 even if
     * already accepted).
     */
    public function accept(Request $request, AcceptPolicyUseCase $useCase): JsonResponse
    {
        $useCase->execute((int) $request->user()->id, $request->ip());

        return ApiResponse::success(null, 'Policy accepted');
    }
}
