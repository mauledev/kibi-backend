<?php

use App\Common\Audit\AuditLoggerInterface;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\Tutor\Application\UseCases\UpdateTutor\UpdateTutorInput;
use App\Modules\Tutor\Application\UseCases\UpdateTutor\UpdateTutorUseCase;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;

describe('UpdateTutorUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(TutorRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new UpdateTutorUseCase(
            repository: $this->repo,
            audit: $this->audit,
        );

        $this->input = new UpdateTutorInput(
            userUuid: 'user-uuid-1',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: 'Vega',
            phone: '5551234567',
            occupation: 'Engineer',
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Tutor entity representing the state before the update.
     */
    function updateTutorMakeBefore(): Tutor
    {
        return new Tutor(
            id: 1,
            uuid: 'profile-uuid-1',
            userId: 1,
            userUuid: 'user-uuid-1',
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: null,
            phone: null,
            status: 'active',
            occupation: null,
            createdAt: new DateTime,
        );
    }

    /**
     * Build a Tutor entity representing the state after the update.
     */
    function updateTutorMakeAfter(): Tutor
    {
        return new Tutor(
            id: 1,
            uuid: 'profile-uuid-1',
            userId: 1,
            userUuid: 'user-uuid-1',
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: 'Vega',
            phone: '5551234567',
            status: 'active',
            occupation: 'Engineer',
            createdAt: new DateTime,
        );
    }

    it('updates the tutor and returns the updated entity', function () {
        $before = updateTutorMakeBefore();
        $after = updateTutorMakeAfter();

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('user-uuid-1')
            ->andReturn($before);

        $this->repo->shouldReceive('update')
            ->once()
            ->andReturn($after);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($this->input);

        expect($result)->toBeInstanceOf(Tutor::class);
        expect($result->getLastNameMaternal())->toBe('Vega');
        expect($result->getPhone())->toBe('5551234567');
        expect($result->getOccupation())->toBe('Engineer');
    });

    it('throws TutorNotFoundException when the tutor does not exist', function () {
        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('user-uuid-1')
            ->andReturn(null);

        $this->repo->shouldNotReceive('update');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(TutorNotFoundException::class);
    });

    it('logs an audit entry with structBefore and structAfter', function () {
        $before = updateTutorMakeBefore();
        $after = updateTutorMakeAfter();

        $this->repo->shouldReceive('findByUserUuid')->once()->andReturn($before);
        $this->repo->shouldReceive('update')->once()->andReturn($after);

        $this->audit->shouldReceive('log')
            ->once()
            ->with(
                'tutor.update',
                1,
                1,
                null,
                Mockery::on(fn ($structBefore) => is_array($structBefore) && $structBefore !== []),
                Mockery::on(fn ($structAfter) => is_array($structAfter) && $structAfter !== []),
            );

        $this->useCase->execute($this->input);
    });
});
