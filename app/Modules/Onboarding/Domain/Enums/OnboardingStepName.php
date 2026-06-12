<?php

namespace App\Modules\Onboarding\Domain\Enums;

enum OnboardingStepName: string
{
    case CompanyData = 'company-data';
    case Branding = 'branding';
    case CreateSchool = 'create-school';
}
