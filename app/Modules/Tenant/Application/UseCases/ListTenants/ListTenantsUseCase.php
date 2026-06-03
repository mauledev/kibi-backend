<?php

namespace App\Modules\Tenant\Application\UseCases\ListTenants;

use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;

class ListTenantsUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Return a paginated list of all tenants with their owners.
     *
     * @return array{items: Tenant[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function execute(int $page = 1, int $perPage = 20): array
    {
        return $this->tenants->listPaginated($perPage, $page);
    }
}
