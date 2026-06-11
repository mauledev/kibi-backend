<?php

namespace App\Modules\Onboarding\Domain\Enums;

enum OnboardingStepStatusEnum: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
