<?php

use App\Modules\Student\Application\UseCases\GetStudent\GetStudentInput;
use App\Modules\Student\Application\UseCases\GetStudent\GetStudentUseCase;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\Exceptions\StudentNotFoundException;

describe('GetStudentUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(StudentRepositoryInterface::class);
        $this->useCase = new GetStudentUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Student domain entity with test defaults.
     *
     * @param  array<string, mixed>  $overrides
     */
    function getStudentMakeStudent(array $overrides = []): Student
    {
        return new Student(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'profile-uuid-1',
            userId: $overrides['userId'] ?? 1,
            userUuid: $overrides['userUuid'] ?? 'user-uuid-1',
            email: $overrides['email'] ?? 'student@example.com',
            firstName: $overrides['firstName'] ?? 'Ana',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'Torres',
            lastNameMaternal: null,
            phone: null,
            status: $overrides['status'] ?? 'pending',
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

    it('returns the student when found by uuid', function () {
        $student = getStudentMakeStudent(['userUuid' => 'found-uuid']);

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('found-uuid')
            ->andReturn($student);

        $result = $this->useCase->execute(new GetStudentInput(userUuid: 'found-uuid'));

        expect($result)->toBeInstanceOf(Student::class);
        expect($result->getUserUuid())->toBe('found-uuid');
    });

    it('throws StudentNotFoundException when uuid does not exist', function () {
        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute(new GetStudentInput(userUuid: 'nonexistent-uuid')))
            ->toThrow(StudentNotFoundException::class);
    });

    it('calls findByUserUuid on the repository with the provided uuid', function () {
        $student = getStudentMakeStudent(['userUuid' => 'lookup-uuid']);

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('lookup-uuid')
            ->andReturn($student);

        $this->useCase->execute(new GetStudentInput(userUuid: 'lookup-uuid'));
    });
});
