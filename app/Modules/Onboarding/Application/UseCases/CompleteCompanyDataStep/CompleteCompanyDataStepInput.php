<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteCompanyDataStep;

final readonly class CompleteCompanyDataStepInput
{
    /**
     * @param  array<string, mixed>  $fiscalAddress
     */
    public function __construct(
        public int $tenantId,
        public int $actorUserId,
        public string $businessName,
        public string $rfc,
        public array $fiscalAddress,
        public string $primaryContactName,
        public string $primaryContactEmail,
        public string $primaryContactPhone,
    ) {}
}
