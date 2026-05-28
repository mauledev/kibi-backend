<?php

namespace App\Common\Tenant;

readonly class TenantData
{
    public function __construct(
        public int $id,
    ) {}
}
