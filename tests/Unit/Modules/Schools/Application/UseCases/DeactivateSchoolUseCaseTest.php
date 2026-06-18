<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Schools\Application\UseCases\DeactivateSchool\DeactivateSchoolInput;
use App\Modules\Schools\Application\UseCases\DeactivateSchool\DeactivateSchoolUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

describe('DeactivateSchoolUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new DeactivateSchoolUseCase($this->repo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeDeactivatableSchool(string $status): School
    {
        return new School(
            id: 7,
            uuid: 'uuid-1',
            tenantId: 10,
            name: 'Colegio',
            slug: 'colegio',
            address: null,
            phone: null,
            status: $status,
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: null,
        );
    }

    it('throws SchoolNotFoundException when uuid does not resolve', function () {
        $this->repo->shouldReceive('findByUuid')->once()->andReturn(null);
        $this->repo->shouldNotReceive('softDelete');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute(new DeactivateSchoolInput(
            actorUserId: 42,
            uuid: 'missing',
        )))->toThrow(SchoolNotFoundException::class);
    });

    it('soft-deletes the school by its internal id', function () {
        $school = makeDeactivatableSchool('active');
        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('softDelete')->once()->with(7);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new DeactivateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
        ));
    });

    it('soft-deletes a suspended school the same way', function () {
        $school = makeDeactivatableSchool('suspended');
        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('softDelete')->once()->with(7);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new DeactivateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
        ));
    });

    it('writes audit log with action school.deactivate', function () {
        $school = makeDeactivatableSchool('active');
        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('softDelete');

        $this->audit->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?int $userId, ?int $entityId, ?int $schoolId, ?array $structBefore, ?array $structAfter) {
                expect($action)->toBe('school.deactivate');
                expect($userId)->toBe(42);
                expect($entityId)->toBe(7);
                expect($structBefore['uuid'])->toBe('uuid-1');
                expect($structBefore['deleted_at'])->toBeNull();
                expect($structAfter['uuid'])->toBe('uuid-1');
                expect($structAfter['deleted_at'])->toBeString()->not->toBeEmpty();

                return true;
            });

        $this->useCase->execute(new DeactivateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
        ));
    });
});
