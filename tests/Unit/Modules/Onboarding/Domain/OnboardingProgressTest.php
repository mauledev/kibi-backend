<?php

use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Entities\OnboardingStepStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingProgressStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepName;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatusEnum;

/**
 * Build an OnboardingProgress entity with sensible defaults.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeProgress(array $overrides = []): OnboardingProgress
{
    $steps = $overrides['steps'] ?? [
        new OnboardingStepStatus(
            step: 1,
            name: OnboardingStepName::CompanyData,
            status: OnboardingStepStatusEnum::Pending,
            completedAt: null,
        ),
        new OnboardingStepStatus(
            step: 2,
            name: OnboardingStepName::Branding,
            status: OnboardingStepStatusEnum::Pending,
            completedAt: null,
        ),
        new OnboardingStepStatus(
            step: 3,
            name: OnboardingStepName::CreateSchool,
            status: OnboardingStepStatusEnum::Pending,
            completedAt: null,
        ),
    ];

    return new OnboardingProgress(
        id: $overrides['id'] ?? 1,
        uuid: $overrides['uuid'] ?? 'uuid-progress-1',
        tenantId: $overrides['tenantId'] ?? 10,
        currentStep: $overrides['currentStep'] ?? 1,
        status: $overrides['status'] ?? OnboardingProgressStatus::InProgress,
        steps: $steps,
        gracePeriodEndsAt: $overrides['gracePeriodEndsAt'] ?? new DateTimeImmutable('+15 days'),
        createdAt: $overrides['createdAt'] ?? new DateTimeImmutable('2024-01-01'),
        updatedAt: $overrides['updatedAt'] ?? new DateTimeImmutable('2024-01-01'),
    );
}

/**
 * Build step status objects for all 3 steps with controlled statuses.
 *
 * @return array<OnboardingStepStatus>
 */
function makeSteps(
    OnboardingStepStatusEnum $step1Status,
    OnboardingStepStatusEnum $step2Status,
    OnboardingStepStatusEnum $step3Status,
): array {
    $completedAt = new DateTimeImmutable('2024-01-01 12:00:00');

    return [
        new OnboardingStepStatus(
            step: 1,
            name: OnboardingStepName::CompanyData,
            status: $step1Status,
            completedAt: $step1Status === OnboardingStepStatusEnum::Completed ? $completedAt : null,
        ),
        new OnboardingStepStatus(
            step: 2,
            name: OnboardingStepName::Branding,
            status: $step2Status,
            completedAt: $step2Status === OnboardingStepStatusEnum::Completed ? $completedAt : null,
        ),
        new OnboardingStepStatus(
            step: 3,
            name: OnboardingStepName::CreateSchool,
            status: $step3Status,
            completedAt: $step3Status === OnboardingStepStatusEnum::Completed ? $completedAt : null,
        ),
    ];
}

describe('OnboardingProgress', function () {
    // -------------------------------------------------------------------------
    // Grace period predicates
    // -------------------------------------------------------------------------

    it('reports grace period expired when grace_period_ends_at is in the past', function () {
        $progress = makeProgress([
            'gracePeriodEndsAt' => new DateTimeImmutable('-1 day'),
        ]);

        expect($progress->isGracePeriodExpired())->toBeTrue();
    });

    it('reports grace period not expired when grace_period_ends_at is in the future', function () {
        $progress = makeProgress([
            'gracePeriodEndsAt' => new DateTimeImmutable('+1 day'),
        ]);

        expect($progress->isGracePeriodExpired())->toBeFalse();
    });

    // -------------------------------------------------------------------------
    // canAccessFullPanel
    // -------------------------------------------------------------------------

    it('reports canAccessFullPanel true when step 3 status is completed', function () {
        $completedSteps = makeSteps(
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::Completed,
        );
        $progressCompleted = makeProgress([
            'status' => OnboardingProgressStatus::Completed,
            'currentStep' => 3,
            'steps' => $completedSteps,
        ]);

        expect($progressCompleted->canAccessFullPanel())->toBeTrue();
    });

    it('reports canAccessFullPanel false when step 3 is not yet completed', function () {
        $incompleteSteps = makeSteps(
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::InProgress,
        );
        $progressIncomplete = makeProgress([
            'status' => OnboardingProgressStatus::InProgress,
            'currentStep' => 3,
            'steps' => $incompleteSteps,
        ]);

        expect($progressIncomplete->canAccessFullPanel())->toBeFalse();
    });

    // -------------------------------------------------------------------------
    // getEffectiveStatus
    // -------------------------------------------------------------------------

    it('getEffectiveStatus returns suspended when grace expired and not completed', function () {
        $progress = makeProgress([
            'status' => OnboardingProgressStatus::InProgress,
            'gracePeriodEndsAt' => new DateTimeImmutable('-1 day'),
        ]);

        expect($progress->getEffectiveStatus())->toBe(OnboardingProgressStatus::Suspended);
    });

    it('getEffectiveStatus returns completed verbatim regardless of grace period', function () {
        $completedSteps = makeSteps(
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::Completed,
            OnboardingStepStatusEnum::Completed,
        );

        // Even with an expired grace period, completed stays completed
        $progress = makeProgress([
            'status' => OnboardingProgressStatus::Completed,
            'gracePeriodEndsAt' => new DateTimeImmutable('-5 days'),
            'steps' => $completedSteps,
        ]);

        expect($progress->getEffectiveStatus())->toBe(OnboardingProgressStatus::Completed);
    });

    it('getEffectiveStatus returns in_progress when grace not expired and not completed', function () {
        $progress = makeProgress([
            'status' => OnboardingProgressStatus::InProgress,
            'gracePeriodEndsAt' => new DateTimeImmutable('+10 days'),
        ]);

        expect($progress->getEffectiveStatus())->toBe(OnboardingProgressStatus::InProgress);
    });
});
