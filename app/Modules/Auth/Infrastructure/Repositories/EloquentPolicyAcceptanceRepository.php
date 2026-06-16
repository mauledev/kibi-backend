<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\UserPolicyAcceptance as UserPolicyAcceptanceModel;
use App\Modules\Auth\Domain\Contracts\PolicyAcceptanceRepositoryInterface;

class EloquentPolicyAcceptanceRepository implements PolicyAcceptanceRepositoryInterface
{
    /** {@inheritDoc} */
    public function hasAccepted(int $userId, string $policyType, string $version): bool
    {
        return UserPolicyAcceptanceModel::query()
            ->where('user_id', $userId)
            ->where('policy_type', $policyType)
            ->where('version', $version)
            ->exists();
    }

    /** {@inheritDoc} */
    public function record(int $userId, string $policyType, string $version, ?string $ip): void
    {
        // Idempotent: the (user_id, policy_type, version) unique index makes a
        // re-acceptance a no-op on the matched row.
        UserPolicyAcceptanceModel::query()->updateOrCreate(
            ['user_id' => $userId, 'policy_type' => $policyType, 'version' => $version],
            ['accepted_at' => now(), 'ip' => $ip],
        );
    }
}
