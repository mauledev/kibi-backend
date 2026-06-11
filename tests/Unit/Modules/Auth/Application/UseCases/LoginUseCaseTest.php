<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\UseCases\Login\LoginUseCase;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Support\Facades\Hash;

describe('LoginUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new LoginUseCase(
            $this->userRepo,
            $this->tokens,
            $this->roleRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function loginMakeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid',
            isStaff: $overrides['isStaff'] ?? false,
            email: $overrides['email'] ?? 'user@test.com',
            firstName: $overrides['firstName'] ?? 'Test',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: $overrides['lastNameMaternal'] ?? null,
            passwordHash: array_key_exists('passwordHash', $overrides) ? $overrides['passwordHash'] : Hash::make('secret'),
            status: $overrides['status'] ?? 'active',
        );
    }

    it('throws InvalidCredentialsException when user is not found', function () {
        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->with('unknown@test.com')
            ->andReturn(null);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'unknown@test.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when password does not match', function () {
        $user = loginMakeUser(['passwordHash' => Hash::make('correct')]);

        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'user@test.com', password: 'wrong');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user password hash is null', function () {
        $user = loginMakeUser(['passwordHash' => null]);

        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'user@test.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('throws InvalidCredentialsException when user is inactive', function () {
        $user = loginMakeUser(['status' => 'inactive']);

        $this->userRepo->shouldReceive('findByEmail')
            ->once()
            ->andReturn($user);
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'user@test.com', password: 'secret');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });

    it('returns LoginOutput with token on valid credentials', function () {
        $user = loginMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->with(1)->andReturn('plain-text-token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1, null, null, null, null, null);

        $input = new LoginInput(email: 'user@test.com', password: 'secret');
        $output = $this->useCase->execute($input);

        expect($output)->toBeInstanceOf(LoginOutput::class);
        expect($output->token)->toBe('plain-text-token');
        expect($output->email)->toBe('user@test.com');
        expect($output->uuid)->toBe('user-uuid');
        expect($output->isStaff)->toBeFalse();
    });

    it('includes merged permission slugs from all active roles in the output', function () {
        $user = loginMakeUser();

        $permA = new Permission(id: 1, uuid: 'perm-a', categoryId: 1, name: 'A', slug: 'grade.publish');
        $permB = new Permission(id: 2, uuid: 'perm-b', categoryId: 1, name: 'B', slug: 'payment.approve');

        $roleA = new Role(
            id: 10,
            uuid: 'r-a',
            tenantId: 10,
            categoryId: null,
            name: 'Role A',
            slug: 'role_a',
            hierarchyLevel: 4,
            isSystemRole: false,
            permissions: [$permA],
            createdAt: new DateTimeImmutable,
        );
        $roleB = new Role(
            id: 11,
            uuid: 'r-b',
            tenantId: 10,
            categoryId: null,
            name: 'Role B',
            slug: 'role_b',
            hierarchyLevel: 5,
            isSystemRole: false,
            permissions: [$permB],
            createdAt: new DateTimeImmutable,
        );

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([$roleA, $roleB]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'user@test.com', password: 'secret');
        $output = $this->useCase->execute($input);

        expect($output->permissions)->toContain('grade.publish');
        expect($output->permissions)->toContain('payment.approve');
        expect($output->roles)->toHaveCount(2);
    });

    it('deduplicates permission slugs when multiple roles share the same permission', function () {
        $user = loginMakeUser();

        $perm = new Permission(id: 1, uuid: 'perm-shared', categoryId: 1, name: 'Shared', slug: 'role.view');

        $roleA = new Role(
            id: 10,
            uuid: 'r-a',
            tenantId: 10,
            categoryId: null,
            name: 'Role A',
            slug: 'role_a',
            hierarchyLevel: 4,
            isSystemRole: false,
            permissions: [$perm],
            createdAt: new DateTimeImmutable,
        );
        $roleB = new Role(
            id: 11,
            uuid: 'r-b',
            tenantId: 10,
            categoryId: null,
            name: 'Role B',
            slug: 'role_b',
            hierarchyLevel: 5,
            isSystemRole: false,
            permissions: [$perm],
            createdAt: new DateTimeImmutable,
        );

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([$roleA, $roleB]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once();

        $input = new LoginInput(email: 'user@test.com', password: 'secret');
        $output = $this->useCase->execute($input);

        expect(array_count_values($output->permissions)['role.view'])->toBe(1);
    });

    it('writes an auth.login audit entry on successful login', function () {
        $user = loginMakeUser();

        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once()->with('auth.login', 1, null, null, null, null, null);

        $input = new LoginInput(email: 'user@test.com', password: 'secret');
        $this->useCase->execute($input);
    });

    it('logs auth.login_failed with the attempted email and never the password', function () {
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);

        $captured = null;
        $this->audit->shouldReceive('log')->once()->withArgs(function (...$args) use (&$captured) {
            $captured = $args;

            return $args[0] === 'auth.login_failed';
        });

        $input = new LoginInput(email: 'attacker@test.com', password: 'super-secret-pw');

        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);

        // The attempted email is stored in struct_after for brute-force correlation...
        expect($captured[5])->toBe(['email' => 'attacker@test.com']);
        expect(json_encode($captured))->not->toContain('super-secret-pw');
    });
});
