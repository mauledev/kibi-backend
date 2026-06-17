<?php

use App\Modules\User\Application\UseCases\GetUser\GetUserInput;
use App\Modules\User\Application\UseCases\GetUser\GetUserUseCase;
use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Entities\RoleAssignment;
use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Exceptions\UserNotFoundException;

describe('GetUserUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new GetUserUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a User domain entity with test defaults.
     *
     * @param  array<int, RoleAssignment>  $roles
     */
    function getUserEntity(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 10,
            uuid: $overrides['uuid'] ?? 'target-uuid',
            email: $overrides['email'] ?? 'target@example.com',
            firstName: $overrides['firstName'] ?? 'Target',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: null,
            phone: null,
            status: $overrides['status'] ?? 'active',
            createdAt: new DateTime,
            emailVerifiedAt: null,
            roles: $overrides['roles'] ?? [],
        );
    }

    it('calls findByUuid on the repository with the provided uuid', function () {
        $user = getUserEntity(['uuid' => 'lookup-uuid']);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->with('lookup-uuid')
            ->andReturn($user);

        $this->useCase->execute(new GetUserInput(uuid: 'lookup-uuid'));
    });

    it('returns the entity when the repository finds it', function () {
        $user = getUserEntity(['uuid' => 'found-uuid', 'email' => 'found@example.com']);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->with('found-uuid')
            ->andReturn($user);

        $result = $this->useCase->execute(new GetUserInput(uuid: 'found-uuid'));

        expect($result)->toBeInstanceOf(User::class);
        expect($result->getUuid())->toBe('found-uuid');
        expect($result->getEmail())->toBe('found@example.com');
    });

    it('throws UserNotFoundException when the repository returns null', function () {
        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        expect(fn () => $this->useCase->execute(new GetUserInput(uuid: 'nonexistent-uuid')))
            ->toThrow(UserNotFoundException::class);
    });

    it('returns the entity with its roles populated', function () {
        $roles = [
            new RoleAssignment(roleUuid: 'role-uuid-student', slug: 'student', name: 'Student', schoolUuid: 'school-uuid-1'),
        ];
        $user = getUserEntity(['roles' => $roles]);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($user);

        $result = $this->useCase->execute(new GetUserInput(uuid: 'target-uuid'));

        expect($result->getRoles())->toHaveCount(1);
        expect($result->getRoles()[0]->slug)->toBe('student');
    });

    it('returns a user with all scalar fields intact', function () {
        $user = getUserEntity([
            'uuid' => 'detail-uuid',
            'email' => 'detail@example.com',
            'firstName' => 'Detail',
            'lastNamePaternal' => 'Name',
            'status' => 'inactive',
        ]);

        $this->repo->shouldReceive('findByUuid')
            ->once()
            ->andReturn($user);

        $result = $this->useCase->execute(new GetUserInput(uuid: 'detail-uuid'));

        expect($result->getEmail())->toBe('detail@example.com');
        expect($result->getFirstName())->toBe('Detail');
        expect($result->getStatus())->toBe('inactive');
    });
});
