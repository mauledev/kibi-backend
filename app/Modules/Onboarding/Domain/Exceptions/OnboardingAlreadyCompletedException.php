<?php

namespace App\Modules\Onboarding\Domain\Exceptions;

use RuntimeException;

class OnboardingAlreadyCompletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Onboarding is already completed. Edit company data and branding from settings.');
    }
}
