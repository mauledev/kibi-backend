<?php

namespace App\Modules\Auth\Application\UseCases\AcceptPolicy;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use App\Modules\Auth\Domain\Contracts\PolicyAcceptanceRepositoryInterface;

/**
 * Records that the current user accepted the Responsible Use Policy (PUR) at the
 * current version, lifting the policy gate for them. Idempotent.
 */
class AcceptPolicyUseCase
{
    public function __construct(
        private readonly PolicyAcceptanceRepositoryInterface $acceptances,
        private readonly AuditLoggerInterface $audit,
        private readonly string $version,
    ) {}

    public function execute(int $userId, ?string $ip): void
    {
        $this->acceptances->record($userId, PolicyAcceptanceChecker::POLICY_TYPE, $this->version, $ip);

        $this->audit->log(
            action: 'policy.accepted',
            userId: $userId,
            structAfter: [
                'policy_type' => PolicyAcceptanceChecker::POLICY_TYPE,
                'version' => $this->version,
            ],
        );
    }
}
