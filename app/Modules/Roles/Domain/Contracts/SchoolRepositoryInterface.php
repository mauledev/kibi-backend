<?php

namespace App\Modules\Roles\Domain\Contracts;

interface SchoolRepositoryInterface
{
    public function findIdByUuid(string $uuid): ?int;
}
