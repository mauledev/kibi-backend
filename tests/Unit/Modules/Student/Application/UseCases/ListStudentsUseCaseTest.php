<?php

use App\Modules\Student\Application\UseCases\ListStudents\ListStudentsInput;
use App\Modules\Student\Application\UseCases\ListStudents\ListStudentsUseCase;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Criteria\StudentListCriteria;
use App\Modules\Student\Domain\Entities\Student;

describe('ListStudentsUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(StudentRepositoryInterface::class);
        $this->useCase = new ListStudentsUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Student domain entity with test defaults.
     */
    function listStudentsMakeStudent(string $userUuid = 'user-uuid'): Student
    {
        return new Student(
            id: 1,
            uuid: 'profile-uuid',
            userId: 1,
            userUuid: $userUuid,
            email: 'student@example.com',
            firstName: 'Ana',
            lastNamePaternal: 'Torres',
            lastNameMaternal: null,
            phone: null,
            status: 'pending',
            birthDate: null,
            nationalId: null,
            enrollmentNumber: null,
            gender: null,
            bloodType: null,
            groupUuid: null,
            groupName: null,
            createdAt: new DateTime,
        );
    }

    /**
     * Build the standard paginated result shape.
     *
     * @param  Student[]  $items
     * @return array{items: Student[], total: int, per_page: int, current_page: int, last_page: int}
     */
    function listStudentsPaginatedResult(array $items = [], int $total = 0, int $perPage = 20, int $page = 1): array
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
            ->with(Mockery::on(function (StudentListCriteria $criteria): bool {
                return $criteria->schoolId === 7
                    && $criteria->perPage === 15
                    && $criteria->page === 2;
            }))
            ->andReturn(listStudentsPaginatedResult());

        $this->useCase->execute(new ListStudentsInput(
            requestedSchoolId: 7,
            perPage: 15,
            page: 2,
        ));
    });

    it('returns the paginated result from the repository', function () {
        $student = listStudentsMakeStudent('result-uuid');
        $expected = listStudentsPaginatedResult([$student], 1, 20, 1);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($expected);

        $result = $this->useCase->execute(new ListStudentsInput);

        expect($result)->toBe($expected);
        expect($result['items'][0]->getUserUuid())->toBe('result-uuid');
    });

    it('passes null schoolId to criteria when no school filter is requested', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (StudentListCriteria $c) => $c->schoolId === null))
            ->andReturn(listStudentsPaginatedResult());

        $this->useCase->execute(new ListStudentsInput);
    });

    it('uses default perPage of 20 and page 1 when not provided', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (StudentListCriteria $c) => $c->perPage === 20 && $c->page === 1))
            ->andReturn(listStudentsPaginatedResult());

        $this->useCase->execute(new ListStudentsInput);
    });

    it('returns an empty items array when no students exist', function () {
        $empty = listStudentsPaginatedResult([], 0);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($empty);

        $result = $this->useCase->execute(new ListStudentsInput);

        expect($result['items'])->toBeArray()->toBeEmpty();
        expect($result['total'])->toBe(0);
    });
});
