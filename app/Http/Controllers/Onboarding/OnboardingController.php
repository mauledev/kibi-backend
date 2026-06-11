<?php

namespace App\Http\Controllers\Onboarding;

use App\Common\Tenant\TenantContext;
use App\Http\Controller;
use App\Http\Requests\Onboarding\CompleteBrandingRequest;
use App\Http\Requests\Onboarding\CompleteCompanyDataRequest;
use App\Http\Requests\Onboarding\CompleteFirstSchoolRequest;
use App\Http\Resources\Onboarding\OnboardingProgressResource;
use App\Http\Response\ApiResponse;
use App\Modules\Onboarding\Application\UseCases\CompleteBrandingStep\CompleteBrandingStepInput;
use App\Modules\Onboarding\Application\UseCases\CompleteBrandingStep\CompleteBrandingStepUseCase;
use App\Modules\Onboarding\Application\UseCases\CompleteCompanyDataStep\CompleteCompanyDataStepInput;
use App\Modules\Onboarding\Application\UseCases\CompleteCompanyDataStep\CompleteCompanyDataStepUseCase;
use App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep\CompleteFirstSchoolStepInput;
use App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep\CompleteFirstSchoolStepUseCase;
use App\Modules\Onboarding\Application\UseCases\GetOnboardingProgress\GetOnboardingProgressInput;
use App\Modules\Onboarding\Application\UseCases\GetOnboardingProgress\GetOnboardingProgressUseCase;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingAlreadyCompletedException;
use App\Modules\Onboarding\Domain\Exceptions\SchoolNotInTenantException;
use App\Modules\Onboarding\Domain\Exceptions\StepOutOfOrderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * GET /onboarding/progress — Return the current onboarding progress for the tenant.
     *
     * Auto-bootstraps if no record exists yet.
     */
    public function getProgress(
        Request $request,
        GetOnboardingProgressUseCase $useCase,
    ): JsonResponse {
        if ($denied = $this->denyIfNotOwner($request)) {
            return $denied;
        }

        $tenantId = app(TenantContext::class)->tenantId;

        $progress = $useCase->execute(new GetOnboardingProgressInput($tenantId));

        return ApiResponse::success((new OnboardingProgressResource($progress))->resolve());
    }

    /**
     * POST /onboarding/steps/company — Complete step 1: company data.
     */
    public function completeCompanyData(
        CompleteCompanyDataRequest $request,
        CompleteCompanyDataStepUseCase $useCase,
    ): JsonResponse {
        if ($denied = $this->denyIfNotOwner($request)) {
            return $denied;
        }

        $tenantId = app(TenantContext::class)->tenantId;

        try {
            $progress = $useCase->execute(new CompleteCompanyDataStepInput(
                tenantId: $tenantId,
                actorUserId: $request->user()->id,
                businessName: $request->validated('business_name'),
                rfc: $request->validated('rfc'),
                fiscalAddress: $request->validated('fiscal_address'),
                primaryContactName: $request->validated('primary_contact_name'),
                primaryContactEmail: $request->validated('primary_contact_email'),
                primaryContactPhone: $request->validated('primary_contact_phone'),
            ));

            return ApiResponse::success((new OnboardingProgressResource($progress))->resolve());
        } catch (OnboardingAlreadyCompletedException $e) {
            return ApiResponse::conflict($e->getMessage());
        }
    }

    /**
     * POST /onboarding/steps/branding — Complete step 2: branding.
     */
    public function completeBranding(
        CompleteBrandingRequest $request,
        CompleteBrandingStepUseCase $useCase,
    ): JsonResponse {
        if ($denied = $this->denyIfNotOwner($request)) {
            return $denied;
        }

        $tenantId = app(TenantContext::class)->tenantId;

        try {
            $progress = $useCase->execute(new CompleteBrandingStepInput(
                tenantId: $tenantId,
                actorUserId: $request->user()->id,
                logoUrl: $request->validated('logo_url'),
                primaryColor: $request->validated('primary_color'),
                secondaryColor: $request->validated('secondary_color'),
            ));

            return ApiResponse::success((new OnboardingProgressResource($progress))->resolve());
        } catch (OnboardingAlreadyCompletedException $e) {
            return ApiResponse::conflict($e->getMessage());
        } catch (StepOutOfOrderException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * POST /onboarding/steps/first-school — Complete step 3: link first school.
     */
    public function completeFirstSchool(
        CompleteFirstSchoolRequest $request,
        CompleteFirstSchoolStepUseCase $useCase,
    ): JsonResponse {
        if ($denied = $this->denyIfNotOwner($request)) {
            return $denied;
        }

        $tenantId = app(TenantContext::class)->tenantId;

        try {
            $progress = $useCase->execute(new CompleteFirstSchoolStepInput(
                tenantId: $tenantId,
                actorUserId: $request->user()->id,
                schoolUuid: $request->validated('school_id'),
            ));

            return ApiResponse::success((new OnboardingProgressResource($progress))->resolve());
        } catch (OnboardingAlreadyCompletedException $e) {
            return ApiResponse::conflict($e->getMessage());
        } catch (StepOutOfOrderException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (SchoolNotInTenantException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * Returns a 403 JsonResponse when the authenticated user is not the tenant
     * Owner, or null when the request is allowed to proceed. Onboarding actions
     * are reserved for the user referenced by tenants.owner_id.
     */
    private function denyIfNotOwner(Request $request): ?JsonResponse
    {
        $ownerId = app(TenantContext::class)->ownerId;

        if ($request->user()?->id !== $ownerId) {
            return ApiResponse::forbidden('Only the owner can perform onboarding');
        }

        return null;
    }
}
