<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\UseCases\StaffLogin\StaffLoginUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\Hash;

describe('StaffLoginUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new StaffLoginUseCase(
            $this->userRepo,
            $this->tokens,
            $this->roleRepo,
            $this->audit,
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

    it('throws InvalidCredentialsException when user is not found', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'unknown@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password does not match', function () {
        $user = staffMakeUser(['passwordHash' => Hash::make('correct')]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'staff@kibi.com', password: 'wrong');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is inactive', function () {
        $user = staffMakeUser(['status' => 'inactive']);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is a tenant user not staff', function () {
        // User has isStaff false — not a staff user
        $user = staffMakeUser(['isStaff' => false]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'tenant_user@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password hash is null', function () {
        $user = staffMakeUser(['passwordHash' => null]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('returns LoginOutput with isStaff true on valid staff credentials', function () {
        $user = staffMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->with(1)->andReturn('staff-token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1, null, null, null, ['ip' => null], null);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');
        $output = $this->useCase->execute($input);

        expect($output)->toBeInstanceOf(LoginOutput::class);
        expect($output->isStaff)->toBeTrue();
        expect($output->token)->toBe('staff-token');
        expect($output->email)->toBe('staff@kibi.com');
    });

    it('writes an auth.login audit entry on successful staff login', function () {
        $user = staffMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1, null, null, null, ['ip' => '203.0.113.7'], null);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret', ip: '203.0.113.7');
        $this->useCase->execute($input);
    });

    it('logs auth.login_failed with reason not_staff and never the password', function () {
        $user = staffMakeUser([
            'isStaff' => false,
            'passwordHash' => Hash::make('super-secret-pw'),
        ]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);

        $captured = null;
        $this->audit->shouldReceive('log')->once()->withArgs(function (...$args) use (&$captured) {
            $captured = $args;

            return $args[0] === 'auth.login_failed';
        });

        $input = new LoginInput(email: 'tenant_user@kibi.com', password: 'super-secret-pw', ip: '203.0.113.7');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);

        expect($captured[5])->toBe(['email' => 'tenant_user@kibi.com', 'ip' => '203.0.113.7', 'reason' => 'not_staff']);
        expect(json_encode($captured))->not->toContain('super-secret-pw');
    });

    it('runs a dummy hash check when the user is not found (anti timing-oracle)', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        // Pin the mitigation: bcrypt must run exactly once even without a user,
        // so response timing cannot reveal whether the email is registered.
        Hash::shouldReceive('check')->once()->andReturnFalse();
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'unknown@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('rejects a null-password-hash user even if the dummy hash check passes', function () {
        $user = staffMakeUser(['passwordHash' => null]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        // The dummy hash runs for timing equalization, but its result must never
        // authenticate an OAuth-only account: the null-hash guard wins.
        Hash::shouldReceive('check')->once()->andReturnTrue();
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });
});
