<?php

use App\Modules\Tutor\Application\UseCases\GetTutor\GetTutorInput;
use App\Modules\Tutor\Application\UseCases\GetTutor\GetTutorUseCase;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;

describe('GetTutorUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(TutorRepositoryInterface::class);
        $this->useCase = new GetTutorUseCase(repository: $this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Tutor domain entity with test defaults.
     *
     * @param  array<string, mixed>  $overrides
     */
    function getTutorMakeTutor(array $overrides = []): Tutor
    {
        return new Tutor(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'profile-uuid-1',
            userId: $overrides['userId'] ?? 1,
            userUuid: $overrides['userUuid'] ?? 'user-uuid-1',
            email: $overrides['email'] ?? 'tutor@example.com',
            firstName: $overrides['firstName'] ?? 'María',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'Rodríguez',
            lastNameMaternal: null,
            phone: null,
            status: $overrides['status'] ?? 'pending',
            occupation: null,
            createdAt: new DateTime,
        );
    }

    it('returns the tutor when found by uuid', function () {
        $tutor = getTutorMakeTutor(['userUuid' => 'found-uuid']);

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('found-uuid')
            ->andReturn($tutor);

        $result = $this->useCase->execute(new GetTutorInput(userUuid: 'found-uuid'));

        expect($result)->toBeInstanceOf(Tutor::class);
        expect($result->getUserUuid())->toBe('found-uuid');
    });

    it('throws TutorNotFoundException when uuid does not exist', function () {
        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute(new GetTutorInput(userUuid: 'nonexistent-uuid')))
            ->toThrow(TutorNotFoundException::class);
    });

    it('calls findByUserUuid on the repository with the provided uuid', function () {
        $tutor = getTutorMakeTutor(['userUuid' => 'lookup-uuid']);

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('lookup-uuid')
            ->andReturn($tutor);

        $this->useCase->execute(new GetTutorInput(userUuid: 'lookup-uuid'));
    });
});
