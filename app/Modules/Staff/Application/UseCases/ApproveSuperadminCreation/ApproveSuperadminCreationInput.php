<?php

namespace App\Modules\Staff\Application\UseCases\ApproveSuperadminCreation;

class ApproveSuperadminCreationInput
{
    public function __construct(
        public readonly string $requestUuid,
        public readonly int $approvedBy,
        public readonly string $code,
    ) {}
}
