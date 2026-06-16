<?php

use App\Models\User;
use App\Models\UserPolicyAcceptance;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Mark a user as having accepted the current Responsible Use Policy,
 * so they pass the `policy.accepted` gate. Superadmins must accept the PUR before
 * using app endpoints; an "already onboarded" superadmin in a test needs this.
 */
function acceptPurFor(User $user): void
{
    UserPolicyAcceptance::create([
        'user_id' => $user->id,
        'policy_type' => 'pur',
        'version' => config('policies.pur.version'),
        'accepted_at' => now(),
    ]);
}
