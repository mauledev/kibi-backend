<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\GetRole;

class GetRoleInput
{
    public function __construct(
        public readonly string $publicId,
    ) {}
}
