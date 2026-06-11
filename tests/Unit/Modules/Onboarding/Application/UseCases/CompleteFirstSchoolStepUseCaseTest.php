<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep\CompleteFirstSchoolStepInput;
use App\Modules\Onboarding\Application\UseCases\CompleteFirstSchoolStep\CompleteFirstSchoolStepUseCase;
use App\Modules\Onboarding\Domain\Contracts\OnboardingRepositoryInterface;
use App\Modules\Onboarding\Domain\Entities\OnboardingProgress;
use App\Modules\Onboarding\Domain\Entities\OnboardingStepStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingProgressStatus;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepName;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatusEnum;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingAlreadyCompletedException;
use App\Modules\Onboarding\Domain\Exceptions\SchoolNotInTenantException;
use App\Modules\Onboarding\Domain\Exceptions\StepOutOfOrderException;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;

describe('CompleteFirstSchoolStepUseCase', function () {
    beforeEach(function () {
        $this->onboardingRepo = Mockery::mock(OnboardingRepositoryInterface::class);
        $this->schoolRepo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new CompleteFirstSchoolStepUseCase(
            $this->onboardingRepo,
            $this->schoolRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    function makeProgressAtStep(int $step): OnboardingProgress
    {
        $completedAt = new DateTimeImmutable('2024-01-01');

        $steps = [
            new OnboardingStepStatus(
                step: 1,
                name: OnboardingStepName::CompanyData,
                status: OnboardingStepStatusEnum::Completed,
                completedAt: $completedAt,
            ),
            new OnboardingStepStatus(
                step: 2,
                name: OnboardingStepName::Branding,
                status: OnboardingStepStatusEnum::Completed,
                completedAt: $completedAt,
            ),
            new OnboardingStepStatus(
                step: 3,
                name: OnboardingStepName::CreateSchool,
                status: OnboardingStepStatusEnum::Pending,
                completedAt: null,
            ),
        ];

        return new OnboardingProgress(
            id: 1,
            uuid: 'uuid-progress',
            tenantId: 10,
            currentStep: $step,
            status: OnboardingProgressStatus::InProgress,
            steps: $steps,
            gracePeriodEndsAt: new DateTimeImmutable('+15 days'),
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
        );
    }

    function makeSchoolForTenant(int $tenantId): School
    {
        return new School(
            id: 5,
            uuid: 'uuid-school-5',
            tenantId: $tenantId,
            name: 'School A',
            slug: 'school-a',
            address: null,
            phone: null,
            status: 'active',
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: null,
        );
    }

    // -------------------------------------------------------------------------
    // Guard clauses
    // -------------------------------------------------------------------------

    it('throws SchoolNotInTenantException when SchoolRepository returns null for the uuid', function () {
        $progress = makeProgressAtStep(3);
        $this->onboardingRepo->shouldReceive('findByTenantId')->once()->with(10)->andReturn($progress);
        $this->schoolRepo->shouldReceive('findByUuid')->once()->with('uuid-school-5')->andReturn(null);

        expect(fn () => $this->useCase->execute(new CompleteFirstSchoolStepInput(
            tenantId: 10,
            actorUserId: 1,
            schoolUuid: 'uuid-school-5',
        )))->toThrow(SchoolNotInTenantException::class);
    });

    it('throws SchoolNotInTenantException when the school exists but belongs to a different tenant', function () {
        $progress = makeProgressAtStep(3);
        $this->onboardingRepo->shouldReceive('findByTenantId')->once()->with(10)->andReturn($progress);

        // School belongs to tenant 99 — not to the current tenant (10)
        $foreignSchool = makeSchoolForTenant(99);
        $this->schoolRepo->shouldReceive('findByUuid')->once()->with('uuid-school-5')->andReturn($foreignSchool);

        expect(fn () => $this->useCase->execute(new CompleteFirstSchoolStepInput(
            tenantId: 10,
            actorUserId: 1,
            schoolUuid: 'uuid-school-5',
        )))->toThrow(SchoolNotInTenantException::class);
    });

    it('throws StepOutOfOrderException when current_step is less than 3', function () {
        // current_step = 2 means step 3 is not yet unlocked
        $progress = makeProgressAtStep(2);
        $this->onboardingRepo->shouldReceive('findByTenantId')->once()->with(10)->andReturn($progress);
        $this->schoolRepo->shouldNotReceive('findByUuid');

        expect(fn () => $this->useCase->execute(new CompleteFirstSchoolStepInput(
            tenantId: 10,
            actorUserId: 1,
            schoolUuid: 'uuid-school-5',
        )))->toThrow(StepOutOfOrderException::class);
    });

    it('throws OnboardingAlreadyCompletedException when progress.status is Completed', function () {
        $completedAt = new DateTimeImmutable('2024-01-01');

        $steps = [
            new OnboardingStepStatus(1, OnboardingStepName::CompanyData, OnboardingStepStatusEnum::Completed, $completedAt),
            new OnboardingStepStatus(2, OnboardingStepName::Branding, OnboardingStepStatusEnum::Completed, $completedAt),
            new OnboardingStepStatus(3, OnboardingStepName::CreateSchool, OnboardingStepStatusEnum::Completed, $completedAt),
        ];

        $progress = new OnboardingProgress(
            id: 1,
            uuid: 'uuid-progress',
            tenantId: 10,
            currentStep: 3,
            status: OnboardingProgressStatus::Completed,
            steps: $steps,
            gracePeriodEndsAt: new DateTimeImmutable('+15 days'),
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
        );

        $this->onboardingRepo->shouldReceive('findByTenantId')->once()->with(10)->andReturn($progress);
        $this->schoolRepo->shouldNotReceive('findByUuid');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute(new CompleteFirstSchoolStepInput(
            tenantId: 10,
            actorUserId: 1,
            schoolUuid: 'uuid-school-5',
        )))->toThrow(OnboardingAlreadyCompletedException::class);
    });
});
