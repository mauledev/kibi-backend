<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User as AuthUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\Tutor\Application\UseCases\LinkTutorToStudent\LinkTutorToStudentInput;
use App\Modules\Tutor\Application\UseCases\LinkTutorToStudent\LinkTutorToStudentUseCase;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\Exceptions\StudentAlreadyLinkedToTutorException;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;

describe('LinkTutorToStudentUseCase', function () {
    beforeEach(function () {
        $this->tutorRepo = Mockery::mock(TutorRepositoryInterface::class);
        $this->globalUsers = Mockery::mock(GlobalUserRepositoryInterface::class);
        $this->mailer = Mockery::mock(MailerInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new LinkTutorToStudentUseCase(
            tutors: $this->tutorRepo,
            globalUsers: $this->globalUsers,
            mailer: $this->mailer,
            audit: $this->audit,
        );

        $this->input = new LinkTutorToStudentInput(
            tutorUserUuid: 'tutor-user-uuid',
            studentUserUuid: 'student-user-uuid',
            relationship: 'mother',
            tenantSlug: 'acme',
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a Tutor entity with test defaults.
     */
    function linkTutorMakeTutor(int $userId = 10, string $userUuid = 'tutor-user-uuid'): Tutor
    {
        return new Tutor(
            id: 1,
            uuid: 'tutor-profile-uuid',
            userId: $userId,
            userUuid: $userUuid,
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
     * Build a stub AuthUser entity representing a student user.
     *
     * @param  bool  $isActivated  When true, emailVerifiedAt will be a non-null DateTime.
     */
    function linkTutorMakeStudentAuthUser(
        int $id = 20,
        string $uuid = 'student-user-uuid',
        bool $isActivated = false
    ): AuthUser {
        return new AuthUser(
            id: $id,
            uuid: $uuid,
            email: 'student@example.com',
            firstName: 'Carlos',
            lastNamePaternal: 'Méndez',
            lastNameMaternal: null,
            passwordHash: null,
            status: $isActivated ? 'active' : 'pending',
            emailVerifiedAt: $isActivated ? new DateTime : null,
        );
    }

    it('links tutor to student and sends magic link when student has no active tutor links', function () {
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $tutor = linkTutorMakeTutor();
        $student = linkTutorMakeStudentAuthUser();

        $this->tutorRepo->shouldReceive('findByUserUuid')
            ->once()
            ->with('tutor-user-uuid')
            ->andReturn($tutor);

        $this->globalUsers->shouldReceive('findByUuid')
            ->once()
            ->with('student-user-uuid')
            ->andReturn($student);

        // Student has no active tutor links yet — magic link will be sent
        $this->tutorRepo->shouldReceive('hasActiveLink')
            ->once()
            ->with(20)
            ->andReturn(false);

        $this->tutorRepo->shouldReceive('linkToStudent')
            ->once()
            ->with(10, 20, 'mother');

        $this->audit->shouldReceive('log')->once();

        // Student is pending (not verified) and has no active links — magic link must be sent
        $this->mailer->shouldReceive('sendActivation')
            ->once()
            ->with('student@example.com', Mockery::type('string'));

        $this->useCase->execute($this->input);
    });

    it('does NOT send magic link when student already has another active tutor link', function () {
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $tutor = linkTutorMakeTutor();
        $student = linkTutorMakeStudentAuthUser();

        $this->tutorRepo->shouldReceive('findByUserUuid')->once()->andReturn($tutor);
        $this->globalUsers->shouldReceive('findByUuid')->once()->andReturn($student);

        // Student already has an active link with another tutor
        $this->tutorRepo->shouldReceive('hasActiveLink')
            ->once()
            ->with(20)
            ->andReturn(true);

        $this->tutorRepo->shouldReceive('linkToStudent')->once();
        $this->audit->shouldReceive('log')->once();

        // Magic link must NOT be sent
        $this->mailer->shouldNotReceive('sendActivation');

        $this->useCase->execute($this->input);
    });

    it('does NOT send magic link when student account is already activated (email_verified_at set)', function () {
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $tutor = linkTutorMakeTutor();
        // Student is already activated — emailVerifiedAt is not null
        $student = linkTutorMakeStudentAuthUser(isActivated: true);

        $this->tutorRepo->shouldReceive('findByUserUuid')->once()->andReturn($tutor);
        $this->globalUsers->shouldReceive('findByUuid')->once()->andReturn($student);

        // First link for this student — but student is already activated
        $this->tutorRepo->shouldReceive('hasActiveLink')
            ->once()
            ->andReturn(false);

        $this->tutorRepo->shouldReceive('linkToStudent')->once();
        $this->audit->shouldReceive('log')->once();

        // Student is already verified — no magic link needed
        $this->mailer->shouldNotReceive('sendActivation');

        $this->useCase->execute($this->input);
    });

    it('throws TutorNotFoundException when tutor does not exist', function () {
        $this->tutorRepo->shouldReceive('findByUserUuid')
            ->once()
            ->with('tutor-user-uuid')
            ->andReturn(null);

        $this->globalUsers->shouldNotReceive('findByUuid');
        $this->tutorRepo->shouldNotReceive('linkToStudent');
        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(TutorNotFoundException::class);
    });

    it('throws a RuntimeException when student user does not exist', function () {
        $tutor = linkTutorMakeTutor();

        $this->tutorRepo->shouldReceive('findByUserUuid')
            ->once()
            ->andReturn($tutor);

        $this->globalUsers->shouldReceive('findByUuid')
            ->once()
            ->with('student-user-uuid')
            ->andReturn(null);

        $this->tutorRepo->shouldNotReceive('hasActiveLink');
        $this->tutorRepo->shouldNotReceive('linkToStudent');
        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(RuntimeException::class);
    });

    it('throws StudentAlreadyLinkedToTutorException when the DB unique constraint is violated', function () {
        $tutor = linkTutorMakeTutor();
        $student = linkTutorMakeStudentAuthUser();

        $this->tutorRepo->shouldReceive('findByUserUuid')->once()->andReturn($tutor);
        $this->globalUsers->shouldReceive('findByUuid')->once()->andReturn($student);

        $this->tutorRepo->shouldReceive('hasActiveLink')->once()->andReturn(false);

        // The DB::transaction throws a unique constraint violation — simulates duplicate active link
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new UniqueConstraintViolationException('', '', [], new PDOException));

        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(StudentAlreadyLinkedToTutorException::class);
    });

    it('writes an audit log entry when the link is created', function () {
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (callable $cb) => $cb());

        $tutor = linkTutorMakeTutor();
        $student = linkTutorMakeStudentAuthUser();

        $this->tutorRepo->shouldReceive('findByUserUuid')->once()->andReturn($tutor);
        $this->globalUsers->shouldReceive('findByUuid')->once()->andReturn($student);
        $this->tutorRepo->shouldReceive('hasActiveLink')->once()->andReturn(true);
        $this->tutorRepo->shouldReceive('linkToStudent')->once();
        $this->mailer->shouldNotReceive('sendActivation');

        $this->audit->shouldReceive('log')
            ->once()
            ->with(
                'tutor.link_student',
                Mockery::type('int'),
                Mockery::any(),
                null,
                null,
                Mockery::on(fn ($structAfter) => is_array($structAfter) && $structAfter !== []),
            );

        $this->useCase->execute($this->input);
    });
});
