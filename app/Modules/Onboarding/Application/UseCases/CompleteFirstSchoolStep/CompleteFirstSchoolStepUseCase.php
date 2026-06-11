<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Exceptions\SchoolNotInTenantException;
use App\Modules\Onboarding\Domain\Exceptions\StepOutOfOrderException;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class CompleteFirstSchoolStepUseCase
{
    public function __construct(
        private readonly OnboardingRepositoryInterface $onboarding,
        private readonly SchoolRepositoryInterface $schools,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Complete step 3 (first school) of the onboarding wizard.
     *
     * Business rules:
     * - Auto-bootstraps the onboarding record if missing (legacy tenants).
     * - Throws StepOutOfOrderException if current_step < 3.
     * - Resolves the school by UUID via Schools repo (tenant-scoped).
     *   If null or school belongs to a different tenant → SchoolNotInTenantException.
     * - Idempotent: if step 3 is already completed, returns current progress.
     * - On first completion: marks step 3 completed and sets overall status to 'completed'.
     *   Writes an audit log entry.
     *
     * @throws StepOutOfOrderException When current_step < 3.
     * @throws SchoolNotInTenantException When the school UUID is not found in the tenant.
     */
    public function execute(CompleteFirstSchoolStepInput $input): OnboardingProgress
    {
        $progress = $this->onboarding->findByTenantId($input->tenantId)
            ?? $this->onboarding->bootstrap($input->tenantId);

        if ($progress->getCurrentStep() < 3) {
            throw new StepOutOfOrderException;
        }

        // Validate the school belongs to this tenant (repo is tenant-scoped)
        $school = $this->schools->findByUuid($input->schoolUuid);

        if ($school === null || $school->getTenantId() !== $input->tenantId) {
            throw new SchoolNotInTenantException;
        }

        // Idempotent: if already completed, return current progress
        if ($progress->isStepCompleted(3)) {
            return $progress;
        }

        DB::transaction(function () use ($progress, $input): void {
            $this->onboarding->markStepCompleted($progress->getId(), 3);
            $this->onboarding->markProgressCompleted($progress->getId());

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
