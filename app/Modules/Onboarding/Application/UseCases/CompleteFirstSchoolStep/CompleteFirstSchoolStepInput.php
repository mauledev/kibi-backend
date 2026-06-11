<?php

namespace App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep;

final readonly class CompleteFirstSchoolStepInput
{
    public function __construct(
        public int $tenantId,
        public int $actorUserId,
        public string $schoolUuid,
    ) {}
}
