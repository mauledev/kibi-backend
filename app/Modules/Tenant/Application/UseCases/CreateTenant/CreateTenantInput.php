<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Application\UseCases\CreateTenant;

class CreateTenantInput
{
    public function __construct(
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly string $ownerEmail,
        public readonly string $ownerFirstName,
        public readonly string $ownerLastNamePaternal,
        public readonly ?string $ownerLastNameMaternal,
    ) {}
}
