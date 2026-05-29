<?php

use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolInput;
use App\Modules\Schools\Application\UseCases\GetSchool\GetSchoolUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

describe('GetSchoolUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->useCase = new GetSchoolUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeGetSchoolEntity(array $overrides = []): School
    {
        return new School(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'uuid-school-1',
            tenantId: $overrides['tenantId'] ?? 10,
            name: $overrides['name'] ?? 'Colegio Kibi',
            slug: $overrides['slug'] ?? 'colegio-kibi',
            address: null,
            phone: null,
            status: $overrides['status'] ?? 'active',
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: $overrides['deletedAt'] ?? null,
        );
    }

    it('calls findByUuid with the provided uuid', function () {
        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->with('target-uuid')
            ->andReturn(makeGetSchoolEntity(['uuid' => 'target-uuid']));

        $this->useCase->execute(new GetSchoolInput('target-uuid'));
    });

    it('returns the entity provided by the repository', function () {
        $school = makeGetSchoolEntity(['uuid' => 'exact-uuid']);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($school);

        $result = $this->useCase->execute(new GetSchoolInput('exact-uuid'));

        expect($result)->toBe($school);
    });

    it('throws SchoolNotFoundException when repository returns null', function () {
        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->andReturn(null);

        expect(fn () => $this->useCase->execute(new GetSchoolInput('missing-uuid')))
            ->toThrow(SchoolNotFoundException::class);
    });

    it('throws SchoolNotFoundException when entity is soft-deleted', function () {
        $school = makeGetSchoolEntity([
            'deletedAt' => new DateTimeImmutable('2024-06-01'),
        ]);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($school);

        expect(fn () => $this->useCase->execute(new GetSchoolInput('uuid-school-1')))
            ->toThrow(SchoolNotFoundException::class);
    });
});
