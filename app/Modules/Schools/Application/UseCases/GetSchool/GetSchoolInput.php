<?php

namespace App\Modules\Schools\Application\UseCases\GetSchool;

final readonly class GetSchoolInput
{
    public function __construct(
        public string $uuid,
    ) {}
}
