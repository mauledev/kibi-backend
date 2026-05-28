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
            tenantId: array_key_exists('tenantId', $overrides) ? $overrides['tenantId'] : null,
            email: $overrides['email'] ?? 'staff@kibi.com',
            fullName: $overrides['fullName'] ?? 'Staff Member',
            passwordHash: array_key_exists('passwordHash', $overrides) ? $overrides['passwordHash'] : Hash::make('secret'),
            status: $overrides['status'] ?? 'active',
        );
    }

    it('throws InvalidCredentialsException when user is not found', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);

        $input = new LoginInput(email: 'unknown@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password does not match', function () {
        $user = staffMakeUser(['passwordHash' => Hash::make('correct')]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'wrong');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is inactive', function () {
        $user = staffMakeUser(['status' => 'inactive']);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is a tenant user not staff', function () {
        // User has a tenantId — not a staff user
        $user = staffMakeUser(['tenantId' => 5]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);

        $input = new LoginInput(email: 'tenant_user@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password hash is null', function () {
        $user = staffMakeUser(['passwordHash' => null]);

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('returns LoginOutput with isStaff true on valid staff credentials', function () {
        $user = staffMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->with(1)->andReturn('staff-token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');
        $output = $this->useCase->execute($input);

        expect($output)->toBeInstanceOf(LoginOutput::class);
        expect($output->isStaff)->toBeTrue();
        expect($output->token)->toBe('staff-token');
        expect($output->email)->toBe('staff@kibi.com');
    });

    it('writes audit log on successful staff login', function () {
        $user = staffMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1);

        $input = new LoginInput(email: 'staff@kibi.com', password: 'secret');
        $this->useCase->execute($input);
    });
});
