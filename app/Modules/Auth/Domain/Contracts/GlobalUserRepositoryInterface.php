<?php

namespace App\Modules\Auth\Domain\Contracts;

use App\Modules\Auth\Domain\Entities\User;

/**
 * Repository for user operations that cross tenant/staff boundaries.
 * Used exclusively by staff-level UseCases (e.g. tenant creation) where
 * neither tenant scope nor staff scope applies.
 */
interface GlobalUserRepositoryInterface
{
    /**
     * Return true when any user row with the given email exists,
     * regardless of tenant or staff status.
     */
    public function existsByEmail(string $email): bool;

    /**
     * Create a new tenant owner user in a pending state.
     * The user has no password, no email_verified_at and no tenant_id yet.
     *
     * @param  string  $email  Owner's email address.
     * @param  string  $firstName  Owner's first name.
     * @param  string  $lastNamePaternal  Owner's paternal last name.
     * @param  string|null  $lastNameMaternal  Owner's maternal last name (optional).
     */
    public function createPending(
        string $email,
        string $firstName,
        string $lastNamePaternal,
        ?string $lastNameMaternal,
    ): User;

    /**
     * Set the tenant_id on a user row.
     * Called inside the tenant creation transaction after the tenant record exists.
     */
    public function setTenantId(int $userId, int $tenantId): void;

    /**
     * Find a user by their public UUID, regardless of tenant or staff status.
     *
     * Returns null when no user with the given UUID exists.
     */
    public function findByUuid(string $uuid): ?User;
}
