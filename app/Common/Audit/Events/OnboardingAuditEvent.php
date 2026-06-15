<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Onboarding module — Owner wizard lifecycle.
 */
enum OnboardingAuditEvent: string implements AuditEvent
{
    case START = 'onboarding.start';
    case STEP_COMPLETE = 'onboarding.step_complete';
    case FINISH = 'onboarding.finish';
}
