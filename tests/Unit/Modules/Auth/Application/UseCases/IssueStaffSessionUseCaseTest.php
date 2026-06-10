<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\UseCases\StaffLogin\IssueStaffSessionUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;

describe('IssueStaffSessionUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);

        $this->useCase = new IssueStaffSessionUseCase(
            $this->userRepo,
            $this->roleRepo,
            $this->tokens,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    it('builds a staff LoginOutput and writes the audit log', function () {
        $user = new User(
            id: 1,
            uuid: 'staff-uuid',
            isStaff: true,
            email: 'staff@kibi.com',
            firstName: 'Staff',
            lastNamePaternal: 'Member',
            lastNameMaternal: null,
            passwordHash: 'irrelevant',
            status: 'active',
        );

        $this->userRepo->shouldReceive('findById')->once()->with(1)->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->with(1)->andReturn('staff-token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1);

        $output = $this->useCase->execute(1);

        expect($output)->toBeInstanceOf(LoginOutput::class);
        expect($output->isStaff)->toBeTrue();
        expect($output->token)->toBe('staff-token');
        expect($output->email)->toBe('staff@kibi.com');
    });

    it('throws InvalidCredentialsException when the user no longer exists', function () {
        $this->userRepo->shouldReceive('findById')->once()->with(1)->andReturn(null);

        expect(fn () => $this->useCase->execute(1))
            ->toThrow(InvalidCredentialsException::class);
    });
});
