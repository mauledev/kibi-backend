<?php

namespace App\Modules\Tenant\Application\UseCases\UpdateTenant;

readonly class UpdateTenantInput
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public string $status,
    ) {}
}
