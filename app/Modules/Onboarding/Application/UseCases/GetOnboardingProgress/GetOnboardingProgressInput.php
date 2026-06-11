<?php

namespace App\Modules\Onboarding\Application\UseCases\GetOnboardingProgress;

final readonly class GetOnboardingProgressInput
{
    public function __construct(public int $tenantId) {}
}
