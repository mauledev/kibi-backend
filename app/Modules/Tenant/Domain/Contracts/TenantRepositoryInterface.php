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

    /**
     * Return a paginated list of all tenants with their owners.
     *
     * @return array{items: Tenant[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function listPaginated(int $perPage, int $page): array;

    /** Find a tenant by its UUID. Returns null when not found. */
    public function findByUuid(string $uuid): ?Tenant;

    /** Find a tenant by its UUID and eager-load the owner. Returns null when not found. */
    public function findByUuidWithOwner(string $uuid): ?Tenant;

    /**
     * Update a tenant's mutable fields by internal ID.
     *
     * @param  string  $name  New display name.
     * @param  string  $slug  New URL slug.
     * @param  string  $status  New lifecycle status.
     */
    public function update(int $id, string $name, string $slug, string $status): Tenant;

    /** Soft-delete a tenant by internal ID. */
    public function delete(int $id): void;
}
