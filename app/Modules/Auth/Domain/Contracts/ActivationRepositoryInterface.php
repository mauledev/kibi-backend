<?php

declare(strict_types=1);

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
     * Activate the user account and the associated tenant in one shot.
     *
     * Sets users.password_hash, users.email_verified_at = now(), and
     * tenants.status = 'active' for the tenant the user belongs to.
     */
    public function activate(int $userId, string $passwordHash, int $tenantId): void;
}
