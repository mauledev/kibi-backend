<?php

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Contracts\PolicyAcceptanceRepositoryInterface;

/**
 * Decides whether a user still has to accept the Responsible Use Policy (PUR).
 *
 * A user must accept when (a) one of their roles is in `policies.pur.required_roles`
 * AND (b) they have no acceptance record for the current `policies.pur.version`.
 * The version and required roles are injected from config (see AppServiceProvider).
 */
class PolicyAcceptanceChecker
{
    public const POLICY_TYPE = 'pur';

    /**
     * @param  array<string>  $requiredRoles
     */
    public function __construct(
        private readonly PolicyAcceptanceRepositoryInterface $acceptances,
        private readonly string $version,
        private readonly array $requiredRoles,
    ) {}

    /**
     * @param  array<string>  $roleSlugs  The user's active role slugs.
     */
    public function mustAccept(int $userId, array $roleSlugs): bool
    {
        if (array_intersect($roleSlugs, $this->requiredRoles) === []) {
            return false;
        }

        return ! $this->acceptances->hasAccepted($userId, self::POLICY_TYPE, $this->version);
    }
}
