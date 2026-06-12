<?php

namespace App\Http\Controllers\Me;

use App\Http\Controller;
use App\Http\Response\ApiResponse;
use App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress\ComputeOnboardingProgressInput;
use App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress\ComputeOnboardingProgressUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Onboarding progress of the authenticated user (the invited member).
 *
 * Read-only and DERIVED — the percentage is computed on the fly from the user's
 * existing data. No table, no stored state.
 */
class MeOnboardingController extends Controller
{
    /**
     * GET /me/onboarding
     *
     * Responds 200 with { percent, completed, missing, is_complete }.
     */
    public function show(Request $request, ComputeOnboardingProgressUseCase $useCase): JsonResponse
    {
        $user = $request->user();

        $progress = $useCase->execute(new ComputeOnboardingProgressInput(
            fields: [
                'first_name' => $user->first_name,
                'last_name_paternal' => $user->last_name_paternal,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            roleSlugs: [],
        ));

        return ApiResponse::success($progress);
    }
}
