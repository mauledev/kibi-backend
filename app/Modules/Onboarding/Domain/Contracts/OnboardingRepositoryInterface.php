<?php

namespace App\Modules\Onboarding\Domain\Contracts;

use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;

interface OnboardingRepositoryInterface
{
    /**
     * Find the onboarding progress record for the given tenant.
     * Returns null if no record exists yet.
     */
    public function findByTenantId(int $tenantId): ?OnboardingProgress;

    /**
     * Create a new onboarding progress record for the tenant with all three
     * steps initialised (step 1 as 'in_progress', steps 2 and 3 as 'pending').
     * The grace period is computed from OnboardingProgress::GRACE_PERIOD_DAYS.
     * Idempotent: if a record already exists for the tenant, returns it unchanged.
     */
    public function bootstrap(int $tenantId): OnboardingProgress;

    /**
     * Mark a single step as 'completed' and record the completion timestamp.
     */
    public function markStepCompleted(int $progressId, int $step): void;

    /**
     * Mark a single step as 'in_progress'.
     */
    public function markStepInProgress(int $progressId, int $step): void;

    /**
     * Advance the current_step pointer on the progress record.
     */
    public function advanceCurrentStep(int $progressId, int $nextStep): void;

    /**
     * Set the overall progress status to 'completed'.
     */
    public function markProgressCompleted(int $progressId): void;
}
