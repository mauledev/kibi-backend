<?php

namespace App\Modules\Onboarding\Domain\Enums;

enum OnboardingProgressStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    /**
     * Suspended is a read-time computation — never persisted to the database.
     * It is returned by OnboardingProgress::getEffectiveStatus() when the
     * grace period has expired and the onboarding is not yet completed.
     */
    case Suspended = 'suspended';
}
