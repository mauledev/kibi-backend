<?php

namespace App\Modules\Onboarding\Domain\Entities;

use App\Modules\Onboarding\Domain\Enums\OnboardingProgressStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatusEnum;
use DateTimeImmutable;

/**
 * Onboarding progress domain entity for a tenant.
 * Pure PHP — no framework dependencies.
 *
 * The 'suspended' status is a read-time computation:
 * it is returned by getEffectiveStatus() but never persisted.
 */
final class OnboardingProgress
{
    /** Number of days from tenant creation during which the owner can complete onboarding. */
    public const GRACE_PERIOD_DAYS = 15;

    /**
     * @param  OnboardingStepStatus[]  $steps
     */
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $tenantId,
        private readonly int $currentStep,
        private readonly OnboardingProgressStatus $status,
        private readonly array $steps,
        private readonly DateTimeImmutable $gracePeriodEndsAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /** Returns the internal surrogate key (Infrastructure use only). */
    public function getId(): int
    {
        return $this->id;
    }

    /** Returns the public UUID used in routes and responses. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Returns the tenant this onboarding record belongs to. */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /** Returns the current step number (1, 2, or 3). */
    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    /** Returns the persisted progress status (in_progress or completed). */
    public function getStatus(): OnboardingProgressStatus
    {
        return $this->status;
    }

    /**
     * Returns all step status records.
     *
     * @return OnboardingStepStatus[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /** Returns the date/time the grace period expires. */
    public function getGracePeriodEndsAt(): DateTimeImmutable
    {
        return $this->gracePeriodEndsAt;
    }

    /** Returns the creation timestamp. */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Returns the last-updated timestamp. */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Returns true when the 15-day grace period has passed. */
    public function isGracePeriodExpired(): bool
    {
        return $this->gracePeriodEndsAt < new DateTimeImmutable;
    }

    /**
     * Returns true when the owner has completed all three onboarding steps
     * and can access the full application panel.
     */
    public function canAccessFullPanel(): bool
    {
        $step3 = $this->findStep(3);

        return $step3 !== null && $step3->getStatus() === OnboardingStepStatusEnum::Completed;
    }

    /**
     * Returns the effective status, computing 'suspended' at read-time
     * when the grace period has expired but onboarding is not completed.
     * Suspended is never persisted.
     */
    public function getEffectiveStatus(): OnboardingProgressStatus
    {
        if ($this->status === OnboardingProgressStatus::Completed) {
            return $this->status;
        }

        if ($this->isGracePeriodExpired()) {
            return OnboardingProgressStatus::Suspended;
        }

        return $this->status;
    }

    /** Finds a step by its number. Returns null if not found. */
    public function findStep(int $step): ?OnboardingStepStatus
    {
        foreach ($this->steps as $stepStatus) {
            if ($stepStatus->getStep() === $step) {
                return $stepStatus;
            }
        }

        return null;
    }

    /** Returns true when the given step number has been completed. */
    public function isStepCompleted(int $step): bool
    {
        $stepStatus = $this->findStep($step);

        return $stepStatus !== null && $stepStatus->isCompleted();
    }
}
