<?php

use App\Modules\Schools\Application\UseCases\ListSchools\ListSchoolsInput;
use App\Modules\Schools\Application\UseCases\ListSchools\ListSchoolsUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;

describe('ListSchoolsUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->useCase = new ListSchoolsUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeSchoolEntity(array $overrides = []): School
    {
        return new School(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'uuid-school-'.($overrides['id'] ?? 1),
            tenantId: $overrides['tenantId'] ?? 10,
            name: $overrides['name'] ?? 'Colegio Kibi',
            slug: $overrides['slug'] ?? 'colegio-kibi',
            address: null,
            phone: null,
            status: $overrides['status'] ?? 'active',
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: null,
        );
    }

    it('calls findAll exactly once on the repository', function () {
        $this->repo->shouldReceive('findAll')
            ->once()
            ->andReturn([]);

        $this->useCase->execute(new ListSchoolsInput);
    });

    it('returns the array of entities provided by the repository', function () {
        $schoolA = makeSchoolEntity(['id' => 1, 'uuid' => 'uuid-a', 'slug' => 'school-a']);
        $schoolB = makeSchoolEntity(['id' => 2, 'uuid' => 'uuid-b', 'slug' => 'school-b']);

        $this->repo->shouldReceive('findAll')
            ->once()
            ->andReturn([$schoolA, $schoolB]);

        $result = $this->useCase->execute(new ListSchoolsInput);

        expect($result)->toHaveCount(2);
        expect($result[0]->getUuid())->toBe('uuid-a');
        expect($result[1]->getUuid())->toBe('uuid-b');
    });

    it('returns an empty array when the repository has no schools', function () {
        $this->repo->shouldReceive('findAll')
            ->once()
            ->andReturn([]);

        $result = $this->useCase->execute(new ListSchoolsInput);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('propagates the exact entity instances returned by the repository', function () {
        $school = makeSchoolEntity(['id' => 99, 'uuid' => 'exact-uuid']);

        $this->repo->shouldReceive('findAll')
            ->once()
            ->andReturn([$school]);

        $result = $this->useCase->execute(new ListSchoolsInput);

        expect($result[0])->toBe($school);
    });
});
