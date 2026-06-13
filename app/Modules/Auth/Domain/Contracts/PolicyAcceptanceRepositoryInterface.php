<?php

namespace App\Modules\Auth\Domain\Contracts;

interface PolicyAcceptanceRepositoryInterface
{
    /**
     * Whether the user has already accepted the given policy version.
     */
    public function hasAccepted(int $userId, string $policyType, string $version): bool;

    /**
     * Record the user's acceptance of a policy version. Idempotent — accepting
     * the same version twice does not create a duplicate row.
     */
    public function record(int $userId, string $policyType, string $version, ?string $ip): void;
}
