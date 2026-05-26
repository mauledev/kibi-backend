<?php

namespace App\Common\Tenant;

final class TenantContext
{
    public function __construct(
        public readonly int $tenantId,
    ) {}
}
