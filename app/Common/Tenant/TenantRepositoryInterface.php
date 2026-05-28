<?php

namespace App\Common\Tenant;

interface TenantRepositoryInterface
{
    public function findActiveBySlug(string $slug): ?TenantData;
}
