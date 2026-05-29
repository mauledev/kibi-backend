<?php

namespace App\Modules\Tenant\Domain\Contracts;

use App\Modules\Tenant\Domain\Entities\Tenant;

interface TenantRepositoryInterface
{
    /** Find a tenant by its URL slug regardless of status. Returns null when not found. */
    public function findBySlug(string $slug): ?Tenant;

    /**
     * Persist a new tenant record. The owner user must already exist.
     *
     * @param  string  $name  Display name of the tenant.
     * @param  string  $slug  Unique URL slug for subdomain routing.
     * @param  int  $ownerId  Internal ID of the owner user.
     */
    public function create(string $name, string $slug, int $ownerId): Tenant;

    /**
     * Find a tenant by slug with the owner user embedded.
     * Returns the Tenant entity with the owner User populated.
     * Throws when not found (used after creation, so it must exist).
     */
    public function findBySlugWithOwner(string $slug): Tenant;

    /** Transition a tenant's status to 'active'. */
    public function activate(int $id): void;
}
