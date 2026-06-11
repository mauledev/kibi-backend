<?php

use App\Modules\Auth\Application\DTOs\MeOutput;
use App\Modules\Auth\Application\UseCases\GetMe\GetMeUseCase;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;

describe('GetMeUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->useCase = new GetMeUseCase($this->userRepo, $this->roleRepo);
    });

    afterEach(function () {
        Mockery::close();
    });

    function getMeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid',
            isStaff: $overrides['isStaff'] ?? false,
            email: $overrides['email'] ?? 'user@test.com',
            firstName: $overrides['firstName'] ?? 'Test',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: $overrides['lastNameMaternal'] ?? null,
            passwordHash: 'hash',
            status: $overrides['status'] ?? 'active',
        );
    }

    it('throws UserNotFoundException when user does not exist', function () {
        $this->userRepo->shouldReceive('findById')->once()->with(99)->andReturn(null);

        expect(fn () => $this->useCase->execute(99))
            ->toThrow(UserNotFoundException::class);
    });

    it('returns MeOutput with user data when user exists', function () {
        $user = getMeUser();

        $this->userRepo->shouldReceive('findById')->once()->with(1)->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);

        $output = $this->useCase->execute(1);

        expect($output)->toBeInstanceOf(MeOutput::class);
        expect($output->uuid)->toBe('user-uuid');
        expect($output->email)->toBe('user@test.com');
        expect($output->fullName)->toBe('Test User');
        expect($output->isStaff)->toBeFalse();
        expect($output->roles)->toBeArray()->toBeEmpty();
        expect($output->permissions)->toBeArray()->toBeEmpty();
    });

    it('returns merged permission slugs from all active roles', function () {
        $user = getMeUser();

        $perm = new Permission(id: 1, uuid: 'perm-uuid', categoryId: 1, name: 'View Role', slug: 'role.view');
        $role = new Role(
            id: 10, uuid: 'role-uuid', tenantId: 10, categoryId: null, name: 'Director', slug: 'director',
            hierarchyLevel: 4, isSystemRole: false, permissions: [$perm],
            createdAt: new DateTimeImmutable,
        );

        $this->userRepo->shouldReceive('findById')->once()->with(1)->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([$role]);

        $output = $this->useCase->execute(1);

        expect($output->permissions)->toContain('role.view');
        expect($output->roles)->toHaveCount(1);
    });

    it('does not call roleRepo when user is not found', function () {
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);
        $this->roleRepo->shouldNotReceive('findActiveRolesForUser');

        expect(fn () => $this->useCase->execute(1))
            ->toThrow(UserNotFoundException::class);
    });
});
