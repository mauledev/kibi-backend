<?php

namespace App\Modules\Staff\Application\UseCases\RejectSuperadminCreation;

class RejectSuperadminCreationInput
{
    public function __construct(
        public readonly string $requestUuid,
        public readonly int $rejectedBy,
        public readonly string $rejectedByUuid,
        public readonly string $reason,
    ) {}
}
