<?php

namespace App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress;

/**
 * Input for computing a member's onboarding progress.
 *
 * Progress is DERIVED from the user's existing data — nothing is stored. The
 * controller passes the relevant field values plus the user's role slugs (so the
 * required set can grow per role as those data points are defined).
 *
 * @param  array<string, mixed>  $fields  Field name → current value (null/'' = missing).
 * @param  array<int, string>  $roleSlugs  Active role slugs of the user.
 */
final class ComputeOnboardingProgressInput
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int, string>  $roleSlugs
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $roleSlugs = [],
    ) {}
}
