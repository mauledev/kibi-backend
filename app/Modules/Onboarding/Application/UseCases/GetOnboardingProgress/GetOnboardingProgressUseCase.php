<?php

namespace App\Modules\Onboarding\Application\UseCases\GetOnboardingProgress;

use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;

final class GetOnboardingProgressUseCase
{
    public function __construct(
        private readonly OnboardingRepositoryInterface $onboarding,
    ) {}

    /**
     * Return the current onboarding progress for the tenant.
     *
     * Auto-bootstraps if no record exists yet (handles legacy tenants
     * created before the onboarding feature was introduced).
     */
    public function execute(GetOnboardingProgressInput $input): OnboardingProgress
    {
        return $this->onboarding->findByTenantId($input->tenantId)
            ?? $this->onboarding->bootstrap($input->tenantId);
    }
}
