<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\School;
use App\Modules\Roles\Domain\Contracts\SchoolRepositoryInterface;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function findIdByUuid(string $uuid): ?int
    {
        return School::where('uuid', $uuid)->value('id');
    }
}
