<?php

namespace App\Modules\Onboarding\Infrastructure\Repositories;

use App\Models\OnboardingProgress as OnboardingProgressModel;
use App\Models\OnboardingStepStatus as OnboardingStepStatusModel;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Entities\OnboardingStepStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingProgressStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepName;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatusEnum;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class EloquentOnboardingRepository implements OnboardingRepositoryInterface
{
    /** {@inheritDoc} */
    public function findByTenantId(int $tenantId): ?OnboardingProgress
    {
        $model = OnboardingProgressModel::where('tenant_id', $tenantId)
            ->with('stepStatuses')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function bootstrap(int $tenantId): OnboardingProgress
    {
        $existing = $this->findByTenantId($tenantId);

        if ($existing !== null) {
            return $existing;
        }

        $gracePeriodEndsAt = (new DateTimeImmutable)->modify('+'.OnboardingProgress::GRACE_PERIOD_DAYS.' days');

        $model = DB::transaction(function () use ($tenantId, $gracePeriodEndsAt): OnboardingProgressModel {
            $progress = OnboardingProgressModel::create([
                'tenant_id' => $tenantId,
                'current_step' => 1,
                'status' => OnboardingProgressStatus::InProgress->value,
                'grace_period_ends_at' => $gracePeriodEndsAt->format('Y-m-d H:i:s'),
            ]);

            $steps = [
                [
                    'progress_id' => $progress->id,
                    'step' => 1,
                    'name' => OnboardingStepName::CompanyData->value,
                    'status' => OnboardingStepStatusEnum::InProgress->value,
                    'completed_at' => null,
                ],
                [
                    'progress_id' => $progress->id,
                    'step' => 2,
                    'name' => OnboardingStepName::Branding->value,
                    'status' => OnboardingStepStatusEnum::Pending->value,
                    'completed_at' => null,
                ],
                [
                    'progress_id' => $progress->id,
                    'step' => 3,
                    'name' => OnboardingStepName::CreateSchool->value,
                    'status' => OnboardingStepStatusEnum::Pending->value,
                    'completed_at' => null,
                ],
            ];

            OnboardingStepStatusModel::insert($steps);

            return $progress->load('stepStatuses');
        });

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function markStepCompleted(int $progressId, int $step): void
    {
        OnboardingStepStatusModel::where('progress_id', $progressId)
            ->where('step', $step)
            ->update([
                'status' => OnboardingStepStatusEnum::Completed->value,
                'completed_at' => now(),
            ]);
    }

    /** {@inheritDoc} */
    public function markStepInProgress(int $progressId, int $step): void
    {
        OnboardingStepStatusModel::where('progress_id', $progressId)
            ->where('step', $step)
            ->update(['status' => OnboardingStepStatusEnum::InProgress->value]);
    }

    /** {@inheritDoc} */
    public function advanceCurrentStep(int $progressId, int $nextStep): void
    {
        OnboardingProgressModel::where('id', $progressId)
            ->update(['current_step' => $nextStep]);
    }

    /** {@inheritDoc} */
    public function markProgressCompleted(int $progressId): void
    {
        OnboardingProgressModel::where('id', $progressId)
            ->update(['status' => OnboardingProgressStatus::Completed->value]);
    }

    private function toDomain(OnboardingProgressModel $model): OnboardingProgress
    {
        $steps = $model->stepStatuses->map(
            fn (OnboardingStepStatusModel $s) => new OnboardingStepStatus(
                step: $s->step,
                name: OnboardingStepName::from($s->name),
                status: OnboardingStepStatusEnum::from($s->status),
                completedAt: $s->completed_at !== null
                    ? new DateTimeImmutable($s->completed_at->toIso8601String())
                    : null,
            )
        )->all();

        return new OnboardingProgress(
            id: $model->id,
            uuid: $model->uuid,
            tenantId: $model->tenant_id,
            currentStep: $model->current_step,
            status: OnboardingProgressStatus::from($model->status),
            steps: $steps,
            gracePeriodEndsAt: new DateTimeImmutable($model->grace_period_ends_at->toIso8601String()),
            createdAt: new DateTimeImmutable($model->created_at->toIso8601String()),
            updatedAt: new DateTimeImmutable($model->updated_at->toIso8601String()),
        );
    }
}
