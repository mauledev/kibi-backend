<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\DTOs\TwoFactorChallenge;
use App\Modules\Auth\Application\UseCases\StaffLogin\IssueStaffSessionUseCase;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Support\Facades\Hash;

describe('StaffLoginUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->issueSession = Mockery::mock(IssueStaffSessionUseCase::class);
        $this->twoFactor = Mockery::mock(TwoFactorRepositoryInterface::class);
        $this->challenges = Mockery::mock(TwoFactorChallengeRepositoryInterface::class);

        $this->useCase = new StaffLoginUseCase(
            $this->userRepo,
            $this->roleRepo,
            $this->issueSession,
            $this->twoFactor,
            $this->challenges,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function staffMakeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'staff-uuid',
            isStaff: array_key_exists('isStaff', $overrides) ? $overrides['isStaff'] : true,
            email: $overrides['email'] ?? 'staff@kibi.com',
            firstName: $overrides['firstName'] ?? 'Staff',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'Member',
            lastNameMaternal: $overrides['lastNameMaternal'] ?? null,
            passwordHash: array_key_exists('passwordHash', $overrides) ? $overrides['passwordHash'] : Hash::make('secret'),
            status: $overrides['status'] ?? 'active',
        );
    }

    function staffLeaderRole(): Role
    {
        return new Role(
            id: 1,
            uuid: 'role-uuid',
            tenantId: null,
            categoryId: null,
            name: 'Leader',
            slug: 'leader',
            hierarchyLevel: 2,
            isSystemRole: true,
            requiresTwoFactor: true,
        );
    }

    // ---- credential failures (the 2FA gate is never reached) ------------------

    it('throws InvalidCredentialsException when user is not found', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);

        expect(fn () => $this->useCase->execute(new LoginInput(email: 'unknown@kibi.com', password: 'secret')))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password does not match', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()
            ->andReturn(staffMakeUser(['passwordHash' => Hash::make('correct')]));

        expect(fn () => $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'wrong')))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is inactive', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()
            ->andReturn(staffMakeUser(['status' => 'inactive']));

        expect(fn () => $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'secret')))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is a tenant user not staff', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()
            ->andReturn(staffMakeUser(['isStaff' => false]));

        expect(fn () => $this->useCase->execute(new LoginInput(email: 'tenant_user@kibi.com', password: 'secret')))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password hash is null', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()
            ->andReturn(staffMakeUser(['passwordHash' => null]));

        expect(fn () => $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'secret')))
            ->toThrow(InvalidCredentialsException::class);
    });

    // ---- 2FA gate -------------------------------------------------------------

    it('issues the session when no role requires 2FA and the user is not enrolled', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(staffMakeUser());
        $this->twoFactor->shouldReceive('isConfirmed')->once()->with(1)->andReturn(false);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);

        $session = new LoginOutput(
            uuid: 'staff-uuid',
            email: 'staff@kibi.com',
            firstName: 'Staff',
            lastNamePaternal: 'Member',
            lastNameMaternal: null,
            fullName: 'Staff Member',
            isStaff: true,
            token: 'staff-token',
        );
        $this->issueSession->shouldReceive('execute')->once()->with(1)->andReturn($session);

        $output = $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'secret'));

        expect($output)->toBe($session);
        expect($output->isStaff)->toBeTrue();
        expect($output->token)->toBe('staff-token');
    });

    it('returns a setup_required challenge when a role mandates 2FA and the user is not enrolled', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(staffMakeUser());
        $this->twoFactor->shouldReceive('isConfirmed')->with(1)->andReturn(false);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([staffLeaderRole()]);
        $this->challenges->shouldReceive('issue')->once()->with(1)->andReturn('challenge-token');
        $this->issueSession->shouldReceive('execute')->never();

        $output = $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'secret'));

        expect($output)->toBeInstanceOf(TwoFactorChallenge::class);
        expect($output->status)->toBe('setup_required');
        expect($output->challengeToken)->toBe('challenge-token');
    });

    it('returns a required challenge when the user is already enrolled', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(staffMakeUser());
        $this->twoFactor->shouldReceive('isConfirmed')->with(1)->andReturn(true);
        $this->challenges->shouldReceive('issue')->once()->with(1)->andReturn('challenge-token');
        $this->issueSession->shouldReceive('execute')->never();

        $output = $this->useCase->execute(new LoginInput(email: 'staff@kibi.com', password: 'secret'));

        expect($output)->toBeInstanceOf(TwoFactorChallenge::class);
        expect($output->status)->toBe('required');
    });
});
