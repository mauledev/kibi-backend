<?php

use App\Common\Audit\AuditLoggerInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\Student\Application\UseCases\UpdateStudent\UpdateStudentInput;
use App\Modules\Student\Application\UseCases\UpdateStudent\UpdateStudentUseCase;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\Exceptions\StudentNotFoundException;

describe('UpdateStudentUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(StudentRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        DB::shouldReceive('transaction')
            ->andReturnUsing(fn (callable $cb) => $cb());

        $this->useCase = new UpdateStudentUseCase(
            students: $this->repo,
            audit: $this->audit,
        );

        $this->input = new UpdateStudentInput(
            userUuid: 'user-uuid-1',
            firstName: 'Ana',
            lastNamePaternal: 'Torres',
            lastNameMaternal: 'Vega',
            phone: '5551234567',
            birthDate: null,
            nationalId: 'CURP123',
            enrollmentNumber: 'ENR-001',
            gender: 'female',
            bloodType: 'O+',
            groupId: null,
            actorId: 1,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Student entity representing the state before the update.
     */
    function updateStudentMakeBefore(): Student
    {
        return new Student(
            id: 1,
            uuid: 'profile-uuid-1',
            userId: 1,
            userUuid: 'user-uuid-1',
            email: 'student@example.com',
            firstName: 'Ana',
            lastNamePaternal: 'Torres',
            lastNameMaternal: null,
            phone: null,
            status: 'active',
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
     * Build a Student entity representing the state after the update.
     */
    function updateStudentMakeAfter(): Student
    {
        return new Student(
            id: 1,
            uuid: 'profile-uuid-1',
            userId: 1,
            userUuid: 'user-uuid-1',
            email: 'student@example.com',
            firstName: 'Ana',
            lastNamePaternal: 'Torres',
            lastNameMaternal: 'Vega',
            phone: '5551234567',
            status: 'active',
            birthDate: null,
            nationalId: 'CURP123',
            enrollmentNumber: 'ENR-001',
            gender: 'female',
            bloodType: 'O+',
            groupUuid: null,
            groupName: null,
            createdAt: new DateTime,
        );
    }

    it('updates the student and returns the updated entity', function () {
        $before = updateStudentMakeBefore();
        $after = updateStudentMakeAfter();

        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('user-uuid-1')
            ->andReturn($before);

        $this->repo->shouldReceive('update')
            ->once()
            ->andReturn($after);

        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute($this->input);

        expect($result)->toBeInstanceOf(Student::class);
        expect($result->getLastNameMaternal())->toBe('Vega');
        expect($result->getPhone())->toBe('5551234567');
    });

    it('throws StudentNotFoundException when the student does not exist', function () {
        $this->repo->shouldReceive('findByUserUuid')
            ->once()
            ->with('user-uuid-1')
            ->andReturn(null);

        $this->repo->shouldNotReceive('update');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(StudentNotFoundException::class);
    });

    it('logs an audit entry with structBefore and structAfter', function () {
        $before = updateStudentMakeBefore();
        $after = updateStudentMakeAfter();

        $this->repo->shouldReceive('findByUserUuid')->once()->andReturn($before);
        $this->repo->shouldReceive('update')->once()->andReturn($after);

        $this->audit->shouldReceive('log')
            ->once()
            ->with(
                'student.update',
                1,
                1,
                null,
                Mockery::on(fn ($structBefore) => is_array($structBefore) && $structBefore !== []),
                Mockery::on(fn ($structAfter) => is_array($structAfter) && $structAfter !== []),
            );

        $this->useCase->execute($this->input);
    });
});
