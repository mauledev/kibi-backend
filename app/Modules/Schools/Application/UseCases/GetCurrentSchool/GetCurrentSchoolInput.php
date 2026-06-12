<?php

namespace App\Modules\Schools\Application\UseCases\GetCurrentSchool;

final readonly class GetCurrentSchoolInput
{
    public function __construct(
        public int $schoolId,
    ) {}
}
