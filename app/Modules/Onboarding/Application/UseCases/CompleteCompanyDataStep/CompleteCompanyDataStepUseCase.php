<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteCompanyDataStep;

use App\Common\Audit\AuditLoggerInterface;
use App\Models\Tenant;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use Illuminate\Support\Facades\DB;

final class CompleteCompanyDataStepUseCase
{
    public function __construct(
        private readonly OnboardingRepositoryInterface $onboarding,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Complete step 1 (company data) of the onboarding wizard.
     *
     * Business rules:
     * - Auto-bootstraps the onboarding record if missing (legacy tenants).
     * - Updates tenant legal_name, rfc, fiscal_address, contact_name,
     *   contact_email and contact_phone.
     * - Idempotent: if step 1 is already completed, updates tenant data and
     *   returns the current progress without transitioning steps or logging.
     * - On first completion: marks step 1 completed, step 2 in_progress,
     *   advances current_step to 2 and writes an audit log entry.
     */
    public function execute(CompleteCompanyDataStepInput $input): OnboardingProgress
    {
        $progress = $this->onboarding->findByTenantId($input->tenantId)
            ?? $this->onboarding->bootstrap($input->tenantId);

        DB::transaction(function () use ($input, $progress): void {
            Tenant::findOrFail($input->tenantId)->update([
                'legal_name' => $input->businessName,
                'rfc' => $input->rfc,
                'fiscal_address' => $input->fiscalAddress,
                'contact_name' => $input->primaryContactName,
                'contact_email' => $input->primaryContactEmail,
                'contact_phone' => $input->primaryContactPhone,
            ]);

            // Idempotent: skip transition and audit log when already completed
            if ($progress->isStepCompleted(1)) {
                return;
            }

            $this->onboarding->markStepCompleted($progress->getId(), 1);
            $this->onboarding->markStepInProgress($progress->getId(), 2);
            $this->onboarding->advanceCurrentStep($progress->getId(), 2);

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
