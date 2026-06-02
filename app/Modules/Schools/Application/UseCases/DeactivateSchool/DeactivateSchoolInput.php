<?php

namespace App\Modules\Schools\Application\UseCases\DeactivateSchool;

final readonly class DeactivateSchoolInput
{
    public function __construct(
        public int $actorUserId,
        public string $uuid,
    ) {}
}
