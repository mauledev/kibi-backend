<?php

use App\Modules\Auth\Application\DTOs\MeOutput;
use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use App\Modules\Auth\Application\UseCases\GetMe\GetStaffMeUseCase;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;

describe('GetStaffMeUseCase', function () {
    beforeEach(function () {
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->policy = Mockery::mock(PolicyAcceptanceChecker::class);
        $this->policy->shouldReceive('mustAccept')->andReturn(false);
        $this->useCase = new GetStaffMeUseCase($this->userRepo, $this->roleRepo, $this->policy);
    });

    afterEach(function () {
        Mockery::close();
    });

    function staffMeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'staff-uuid',
            isStaff: true,
            email: $overrides['email'] ?? 'staff@kibi.com',
            firstName: $overrides['firstName'] ?? 'Staff',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: $overrides['lastNameMaternal'] ?? null,
            passwordHash: 'hash',
            status: 'active',
        );
    }

    it('throws UserNotFoundException when staff user does not exist', function () {
        $this->userRepo->shouldReceive('findById')->once()->with(99)->andReturn(null);

        expect(fn () => $this->useCase->execute(99))
            ->toThrow(UserNotFoundException::class);
    });

    it('returns MeOutput with isStaff true for staff user', function () {
        $user = staffMeUser();

        $this->userRepo->shouldReceive('findById')->once()->with(1)->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);

        $output = $this->useCase->execute(1);

        expect($output)->toBeInstanceOf(MeOutput::class);
        expect($output->uuid)->toBe('staff-uuid');
        expect($output->isStaff)->toBeTrue();
        expect($output->permissions)->toBeArray()->toBeEmpty();
    });

    it('includes system role permissions in output', function () {
        $user = staffMeUser();

        $perm = new Permission(id: 1, uuid: 'p-uuid', categoryId: 1, name: 'Manage All', slug: 'tenant.manage');
        $role = new Role(
            id: 1, uuid: 'r-uuid', tenantId: null, categoryId: null, name: 'Superadmin', slug: 'superadmin',
            hierarchyLevel: 1, isSystemRole: true, permissions: [$perm],
            createdAt: new DateTimeImmutable,
        );

        $this->userRepo->shouldReceive('findById')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([$role]);

        $output = $this->useCase->execute(1);

        expect($output->permissions)->toContain('tenant.manage');
        expect($output->roles)->toHaveCount(1);
    });

    it('does not call roleRepo when user is not found', function () {
        $this->userRepo->shouldReceive('findById')->once()->andReturn(null);
        $this->roleRepo->shouldNotReceive('findActiveRolesForUser');

        expect(fn () => $this->useCase->execute(1))
            ->toThrow(UserNotFoundException::class);
    });
});
