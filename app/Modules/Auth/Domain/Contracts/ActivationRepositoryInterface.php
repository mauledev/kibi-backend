<?php

namespace App\Modules\Auth\Domain\Contracts;

use App\Modules\Auth\Domain\Entities\User;

/**
 * Repository for account activation operations.
 * Queries the users table without any tenant or staff scope.
 */
interface ActivationRepositoryInterface
{
    /**
     * Find a user by UUID that has not yet activated their account
     * (email_verified_at IS NULL).
     * Returns null when no matching pending user is found.
     */
    public function findPendingByUuid(string $uuid): ?User;

    /**
     * Activate the user account, and the associated tenant when there is one.
     *
     * Sets users.password_hash and users.email_verified_at = now(). When
     * $tenantId is provided (tenant owner activation) it also sets
     * tenants.status = 'active'. For Softlinkia staff users pass null —
     * there is no tenant to activate.
     */
    public function activate(int $userId, string $passwordHash, ?int $tenantId): void;
}
