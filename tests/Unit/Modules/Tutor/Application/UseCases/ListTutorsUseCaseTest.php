<?php

use App\Modules\Tutor\Application\UseCases\ListTutors\ListTutorsInput;
use App\Modules\Tutor\Application\UseCases\ListTutors\ListTutorsUseCase;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Criteria\TutorListCriteria;
use App\Modules\Tutor\Domain\Entities\Tutor;

describe('ListTutorsUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(TutorRepositoryInterface::class);
        $this->useCase = new ListTutorsUseCase(repository: $this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Tutor domain entity with test defaults.
     */
    function listTutorsMakeTutor(string $userUuid = 'user-uuid'): Tutor
    {
        return new Tutor(
            id: 1,
            uuid: 'profile-uuid',
            userId: 1,
            userUuid: $userUuid,
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: null,
            phone: null,
            status: 'pending',
            occupation: null,
            createdAt: new DateTime,
        );
    }

    /**
     * Build the standard paginated result shape.
     *
     * @param  Tutor[]  $items
     * @return array{items: Tutor[], total: int, per_page: int, current_page: int, last_page: int}
     */
    function listTutorsPaginatedResult(array $items = [], int $total = 0, int $perPage = 20, int $page = 1): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    it('calls repository with the correct criteria built from input', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(function (TutorListCriteria $criteria): bool {
                return $criteria->requestedSchoolId === 7
                    && $criteria->perPage === 15
                    && $criteria->page === 2;
            }))
            ->andReturn(listTutorsPaginatedResult());

        $this->useCase->execute(new ListTutorsInput(
            requestedSchoolId: 7,
            perPage: 15,
            page: 2,
        ));
    });

    it('returns the paginated result from the repository', function () {
        $tutor = listTutorsMakeTutor('result-uuid');
        $expected = listTutorsPaginatedResult([$tutor], 1, 20, 1);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($expected);

        $result = $this->useCase->execute(new ListTutorsInput);

        expect($result)->toBe($expected);
        expect($result['items'][0]->getUserUuid())->toBe('result-uuid');
    });

    it('passes null schoolId to criteria when no school filter is requested', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (TutorListCriteria $c) => $c->requestedSchoolId === null))
            ->andReturn(listTutorsPaginatedResult());

        $this->useCase->execute(new ListTutorsInput);
    });

    it('uses default perPage of 20 and page 1 when not provided', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (TutorListCriteria $c) => $c->perPage === 20 && $c->page === 1))
            ->andReturn(listTutorsPaginatedResult());

        $this->useCase->execute(new ListTutorsInput);
    });

    it('returns an empty items array when no tutors exist', function () {
        $empty = listTutorsPaginatedResult([], 0);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($empty);

        $result = $this->useCase->execute(new ListTutorsInput);

        expect($result['items'])->toBeArray()->toBeEmpty();
        expect($result['total'])->toBe(0);
    });
});
