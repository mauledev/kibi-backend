<?php

namespace App\Http\Resources\Onboarding;

use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Entities\OnboardingStepStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises an OnboardingProgress domain entity to JSON.
 *
 * @property OnboardingProgress $resource
 */
class OnboardingProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OnboardingProgress $progress */
        $progress = $this->resource;

        return [
            'uuid' => $progress->getUuid(),
            'current_step' => $progress->getCurrentStep(),
            'status' => $progress->getEffectiveStatus()->value,
            'steps' => array_map(
                fn (OnboardingStepStatus $s) => [
                    'step' => $s->getStep(),
                    'name' => $s->getName()->value,
                    'status' => $s->getStatus()->value,
                    'completed_at' => $s->getCompletedAt()?->format('c'),
                ],
                $progress->getSteps()
            ),
            'grace_period_ends_at' => $progress->getGracePeriodEndsAt()->format('c'),
            'is_grace_period_expired' => $progress->isGracePeriodExpired(),
            'can_access_full_panel' => $progress->canAccessFullPanel(),
            'created_at' => $progress->getCreatedAt()->format('c'),
            'updated_at' => $progress->getUpdatedAt()->format('c'),
        ];
    }
}
