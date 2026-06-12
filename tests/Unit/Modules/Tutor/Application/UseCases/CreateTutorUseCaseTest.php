<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\MailerInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User as AuthUser;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Tutor\Application\UseCases\CreateTutor\CreateTutorInput;
use App\Modules\Tutor\Application\UseCases\CreateTutor\CreateTutorUseCase;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;

describe('CreateTutorUseCase', function () {
    beforeEach(function () {
        $this->users = Mockery::mock(GlobalUserRepositoryInterface::class);
        $this->assignRole = Mockery::mock(AssignRoleToUserUseCase::class);
        $this->tutorRepo = Mockery::mock(TutorRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->mailer = Mockery::mock(MailerInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new CreateTutorUseCase(
            globalUsers: $this->users,
            roles: $this->roleRepo,
            assignRole: $this->assignRole,
            tutors: $this->tutorRepo,
            mailer: $this->mailer,
            audit: $this->audit,
        );

        $this->input = new CreateTutorInput(
            tenantId: 1,
            tenantSlug: 'acme',
            actorUuid: 'actor-uuid-1',
            actorSlug: 'owner',
            schoolUuid: 'school-uuid-1',
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: 'López',
            phone: null,
            occupation: null,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a stub AuthUser entity.
     */
    function createTutorMakeAuthUser(string $uuid = 'new-user-uuid'): AuthUser
    {
        return new AuthUser(
            id: 99,
            uuid: $uuid,
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: 'López',
            passwordHash: null,
            status: 'pending',
        );
    }

    /**
     * Build a stub Role domain entity.
     */
    function createTutorMakeRole(string $slug = 'tutor'): Role
    {
        return new Role(
            id: 4,
            uuid: 'role-tutor-uuid',
            tenantId: 1,
            categoryId: null,
            name: 'Tutor',
            slug: $slug,
            hierarchyLevel: 7,
            isSystemRole: false,
            permissions: [],
        );
    }

    /**
     * Build a stub Tutor entity.
     */
    function createTutorMakeTutor(): Tutor
    {
        return new Tutor(
            id: 10,
            uuid: 'profile-uuid',
            userId: 99,
            userUuid: 'new-user-uuid',
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: 'López',
            phone: null,
            status: 'pending',
            occupation: null,
            createdAt: new DateTime,
        );
    }

    it('creates a pending user, assigns the tutor role, creates the profile, and sends a magic link', function () {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $cb) => $cb());

        $authUser = createTutorMakeAuthUser();
        $role = createTutorMakeRole();
        $tutor = createTutorMakeTutor();

        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->with('tutor@example.com')
            ->andReturn(false);

        $this->roleRepo->shouldReceive('findBySlug')
            ->once()
            ->with('tutor')
            ->andReturn($role);

        $this->users->shouldReceive('createPending')
            ->once()
            ->andReturn($authUser);

        $this->users->shouldReceive('setTenantId')
            ->once()
            ->with(99, 1);

        $this->assignRole->shouldReceive('execute')->once();

        $this->tutorRepo->shouldReceive('create')
            ->once()
            ->andReturn($tutor);

        $this->audit->shouldReceive('log')->once();

        // Magic link must be sent after the transaction completes
        $this->mailer->shouldReceive('sendActivation')
            ->once()
            ->with('tutor@example.com', Mockery::type('string'));

        $result = $this->useCase->execute($this->input);

        expect($result)->toBeInstanceOf(Tutor::class);
    });

    it('throws EmailAlreadyTakenException when email is already taken', function () {
        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->with('tutor@example.com')
            ->andReturn(true);

        $this->roleRepo->shouldNotReceive('findBySlug');
        $this->users->shouldNotReceive('createPending');
        $this->tutorRepo->shouldNotReceive('create');
        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(EmailAlreadyTakenException::class);
    });

    it('throws RoleNotFoundException when tutor role does not exist in the tenant', function () {
        $this->users->shouldReceive('existsByEmail')
            ->once()
            ->andReturn(false);

        $this->roleRepo->shouldReceive('findBySlug')
            ->once()
            ->with('tutor')
            ->andReturn(null);

        $this->users->shouldNotReceive('createPending');
        $this->tutorRepo->shouldNotReceive('create');
        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(RoleNotFoundException::class);
    });

    it('does not send magic link when the DB transaction throws — mailer is only called after successful commit', function () {
        $role = createTutorMakeRole();

        $this->users->shouldReceive('existsByEmail')->once()->andReturn(false);
        $this->roleRepo->shouldReceive('findBySlug')->once()->andReturn($role);

        // DB::transaction fails — nothing inside should commit
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new RuntimeException('DB failure'));

        $this->mailer->shouldNotReceive('sendActivation');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute($this->input))
            ->toThrow(RuntimeException::class);
    });

    it('sets phone on the user record when phone is provided', function () {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $cb) => $cb());

        $authUser = createTutorMakeAuthUser();
        $role = createTutorMakeRole();
        $tutor = createTutorMakeTutor();

        $inputWithPhone = new CreateTutorInput(
            tenantId: 1,
            tenantSlug: 'acme',
            actorUuid: 'actor-uuid-1',
            actorSlug: 'owner',
            schoolUuid: 'school-uuid-1',
            email: 'tutor@example.com',
            firstName: 'María',
            lastNamePaternal: 'Rodríguez',
            lastNameMaternal: null,
            phone: '5551234567',
            occupation: null,
        );

        $this->users->shouldReceive('existsByEmail')->once()->andReturn(false);
        $this->roleRepo->shouldReceive('findBySlug')->once()->andReturn($role);
        $this->users->shouldReceive('createPending')->once()->andReturn($authUser);
        $this->users->shouldReceive('setTenantId')->once();
        $this->assignRole->shouldReceive('execute')->once();
        $this->tutorRepo->shouldReceive('create')->once()->andReturn($tutor);
        $this->audit->shouldReceive('log')->once();
        $this->mailer->shouldReceive('sendActivation')->once();

        $result = $this->useCase->execute($inputWithPhone);

        expect($result)->toBeInstanceOf(Tutor::class);
    });
});
