<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteBrandingStep;

final readonly class CompleteBrandingStepInput
{
    public function __construct(
        public int $tenantId,
        public int $actorUserId,
        public ?string $logoUrl,
        public string $primaryColor,
        public string $secondaryColor,
    ) {}
}
