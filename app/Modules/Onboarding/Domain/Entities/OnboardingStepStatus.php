<?php

namespace App\Modules\Onboarding\Domain\Entities;

use App\Modules\Onboarding\Domain\Enums\OnboardingStepName;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatusEnum;
use DateTimeImmutable;

/**
 * Represents the status of a single onboarding step for a tenant.
 * Pure PHP — no framework dependencies.
 */
final class OnboardingStepStatus
{
    public function __construct(
        private readonly int $step,
        private readonly OnboardingStepName $name,
        private readonly OnboardingStepStatusEnum $status,
        private readonly ?DateTimeImmutable $completedAt,
    ) {}

    /** Returns the step number (1, 2, or 3). */
    public function getStep(): int
    {
        return $this->step;
    }

    /** Returns the step name enum. */
    public function getName(): OnboardingStepName
    {
        return $this->name;
    }

    /** Returns the step status enum. */
    public function getStatus(): OnboardingStepStatusEnum
    {
        return $this->status;
    }

    /** Returns the timestamp when this step was completed, or null. */
    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** Returns true when the step has been completed. */
    public function isCompleted(): bool
    {
        return $this->status === OnboardingStepStatusEnum::Completed;
    }
}
