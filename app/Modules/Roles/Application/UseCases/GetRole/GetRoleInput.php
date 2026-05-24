<?php

namespace App\Modules\Roles\Application\UseCases\GetRole;

class GetRoleInput
{
    public function __construct(
        public readonly string $publicId,
    ) {}
}
