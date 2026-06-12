<?php

namespace App\Modules\MemberOnboarding\Application\UseCases\ComputeOnboardingProgress;

/**
 * Computes a member's onboarding progress as a percentage of required data that
 * has already been filled vs. what is still missing.
 *
 * NOTHING is stored — the percentage is derived from the user's existing data on
 * every call. The required set is intentionally small for the MVP (minimum
 * profile) and is meant to grow per role as each role's data points are defined.
 */
final class ComputeOnboardingProgressUseCase
{
    /**
     * @return array{percent: int, completed: array<int, string>, missing: array<int, string>, is_complete: bool}
     */
    public function execute(ComputeOnboardingProgressInput $input): array
    {
        $required = $this->requiredFields($input->roleSlugs);

        $completed = [];
        $missing = [];

        foreach ($required as $field) {
            $value = $input->fields[$field] ?? null;

            if ($value !== null && $value !== '') {
                $completed[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        $total = count($required);
        $percent = $total === 0 ? 100 : (int) round((count($completed) / $total) * 100);

        return [
            'percent' => $percent,
            'completed' => $completed,
            'missing' => $missing,
            'is_complete' => $missing === [],
        ];
    }

    /**
     * Required fields for the given roles.
     *
     * MVP: minimum profile, derived from existing `users` columns (email + names
     * are set at invite time; `phone` is the realistic missing one).
     *
     * TODO: extend per role — e.g. director-specific fields once their data points
     * (columns/resources) exist. Add a `match`/map on $roleSlugs here.
     *
     * @param  array<int, string>  $roleSlugs
     * @return array<int, string>
     */
    private function requiredFields(array $roleSlugs): array
    {
        return ['first_name', 'last_name_paternal', 'email', 'phone'];
    }
}
