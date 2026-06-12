<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteBrandingStep;

use App\Common\Audit\AuditLoggerInterface;
use App\Models\Tenant;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Enums\OnboardingProgressStatus;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingAlreadyCompletedException;
use App\Modules\Onboarding\Domain\Exceptions\StepOutOfOrderException;
use Illuminate\Support\Facades\DB;

final class CompleteBrandingStepUseCase
{
    public function __construct(
        private readonly OnboardingRepositoryInterface $onboarding,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Complete step 2 (branding) of the onboarding wizard.
     *
     * Business rules:
     * - Auto-bootstraps the onboarding record if missing (legacy tenants).
     * - Throws StepOutOfOrderException if current_step < 2 (step 1 not completed yet).
     * - Updates tenants.branding JSONB with logo_url, primary_color, secondary_color.
     * - Idempotent: if step 2 is already completed, updates branding data and
     *   returns the current progress without transitioning steps or logging.
     * - On first completion: marks step 2 completed, step 3 in_progress,
     *   advances current_step to 3 and writes an audit log entry.
     *
     * @throws OnboardingAlreadyCompletedException When progress.status is Completed.
     * @throws StepOutOfOrderException When step 1 has not been completed yet.
     */
    public function execute(CompleteBrandingStepInput $input): OnboardingProgress
    {
        $progress = $this->onboarding->findByTenantId($input->tenantId)
            ?? $this->onboarding->bootstrap($input->tenantId);

        if ($progress->getStatus() === OnboardingProgressStatus::Completed) {
            throw new OnboardingAlreadyCompletedException;
        }

        if ($progress->getCurrentStep() < 2) {
            throw new StepOutOfOrderException;
        }

        $branding = [
            'logo_url' => $input->logoUrl,
            'primary_color' => $input->primaryColor,
            'secondary_color' => $input->secondaryColor,
        ];

        DB::transaction(function () use ($input, $progress, $branding): void {
            Tenant::findOrFail($input->tenantId)->update([
                'branding' => $branding,
            ]);

            // Idempotent: skip transition and audit log when already completed
            if ($progress->isStepCompleted(2)) {
                return;
            }

            $this->onboarding->markStepCompleted($progress->getId(), 2);
            $this->onboarding->markStepInProgress($progress->getId(), 3);
            $this->onboarding->advanceCurrentStep($progress->getId(), 3);

            $this->audit->log(
                action: 'onboarding.step_completed',
                userId: $input->actorUserId,
                entityId: $progress->getId(),
            );
        });

        // Re-fetch to return the fresh state
        /** @var OnboardingProgress $fresh */
        $fresh = $this->onboarding->findByTenantId($input->tenantId);

        return $fresh;
    }
}
