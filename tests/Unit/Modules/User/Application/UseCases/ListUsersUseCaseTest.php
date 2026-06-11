<?php

use App\Modules\User\Application\UseCases\ListUsers\ListUsersInput;
use App\Modules\User\Application\UseCases\ListUsers\ListUsersUseCase;
use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Criteria\UserListCriteria;
use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Exceptions\SchoolAccessDeniedException;

describe('ListUsersUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new ListUsersUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * Build a User domain entity with test defaults.
     */
    function listUsersEntity(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid-'.($overrides['id'] ?? 1),
            email: $overrides['email'] ?? 'user@example.com',
            firstName: $overrides['firstName'] ?? 'Test',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: null,
            phone: null,
            status: $overrides['status'] ?? 'active',
            createdAt: new DateTime,
            roles: [],
        );
    }

    /**
     * Build the standard paginated result shape the repository returns.
     *
     * @param  User[]  $items
     * @return array{items: User[], total: int, per_page: int, current_page: int, last_page: int}
     */
    function paginatedResult(array $items = [], int $total = 0, int $perPage = 20, int $page = 1): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    it('calls findAllPaginated exactly once on the repository', function () {
        $expected = paginatedResult();

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($expected);

        $this->useCase->execute(new ListUsersInput(isOwner: true));
    });

    it('returns the paginated array verbatim from the repository', function () {
        $user = listUsersEntity(['uuid' => 'result-uuid']);
        $expected = paginatedResult([$user], 1, 20, 1);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($expected);

        $result = $this->useCase->execute(new ListUsersInput(isOwner: true));

        expect($result)->toBe($expected);
        expect($result['items'][0]->getUuid())->toBe('result-uuid');
    });

    it('passes a UserListCriteria built from the input search to the repository', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->search === 'John'))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(search: 'John', isOwner: true));
    });

    it('passes roleSlugs from the input to the criteria', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->roleSlugs === ['student', 'teacher']))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(roleSlugs: ['student', 'teacher'], isOwner: true));
    });

    it('passes status from the input to the criteria', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->status === 'inactive'))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(status: 'inactive', isOwner: true));
    });

    it('passes the unassigned flag from the input to the criteria', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->unassigned === true))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(unassigned: true, isOwner: true));
    });

    it('defaults the unassigned flag to false', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->unassigned === false))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(isOwner: true));
    });

    it('passes perPage and page from the input to the criteria', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->perPage === 10 && $c->page === 3))
            ->andReturn(paginatedResult([], 0, 10, 3));

        $this->useCase->execute(new ListUsersInput(isOwner: true, perPage: 10, page: 3));
    });

    it('uses default perPage=20 and page=1 when input has no explicit pagination', function () {
        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->with(Mockery::on(fn (UserListCriteria $c) => $c->perPage === 20 && $c->page === 1))
            ->andReturn(paginatedResult());

        $this->useCase->execute(new ListUsersInput(isOwner: true));
    });

    it('returns an empty items array when the repository has no results', function () {
        $empty = paginatedResult([], 0);

        $this->repo->shouldReceive('findAllPaginated')
            ->once()
            ->andReturn($empty);

        $result = $this->useCase->execute(new ListUsersInput(isOwner: true));

        expect($result['items'])->toBeArray()->toBeEmpty();
        expect($result['total'])->toBe(0);
    });

    describe('school scope resolution', function () {
        it('gives the owner a null scope (tenant-wide) when no school is requested', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(fn (UserListCriteria $c) => $c->schoolIds === null))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(isOwner: true));
        });

        it('narrows the owner to a single school when X-School-Uuid is present', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(fn (UserListCriteria $c) => $c->schoolIds === [7]))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(isOwner: true, requestedSchoolId: 7));
        });

        it('scopes a non-owner to all their accessible schools when no header is sent', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(fn (UserListCriteria $c) => $c->schoolIds === [3, 5]))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(accessibleSchoolIds: [3, 5]));
        });

        it('narrows a non-owner to the requested school when it is within their access', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(fn (UserListCriteria $c) => $c->schoolIds === [5]))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(accessibleSchoolIds: [3, 5], requestedSchoolId: 5));
        });

        it('throws SchoolAccessDeniedException when a non-owner requests a school outside their access', function () {
            $this->repo->shouldNotReceive('findAllPaginated');

            expect(fn () => $this->useCase->execute(
                new ListUsersInput(accessibleSchoolIds: [3, 5], requestedSchoolId: 9)
            ))->toThrow(SchoolAccessDeniedException::class);
        });

        it('gives a non-owner with no accessible schools an empty scope (no results)', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(fn (UserListCriteria $c) => $c->schoolIds === []))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(accessibleSchoolIds: []));
        });

        it('passes all other criteria fields alongside the resolved scope', function () {
            $this->repo->shouldReceive('findAllPaginated')
                ->once()
                ->with(Mockery::on(function (UserListCriteria $c): bool {
                    return $c->search === 'Maria'
                        && $c->roleSlugs === ['student']
                        && $c->status === 'active'
                        && $c->schoolIds === [5]
                        && $c->perPage === 15
                        && $c->page === 2;
                }))
                ->andReturn(paginatedResult());

            $this->useCase->execute(new ListUsersInput(
                search: 'Maria',
                roleSlugs: ['student'],
                status: 'active',
                accessibleSchoolIds: [5],
                requestedSchoolId: 5,
                perPage: 15,
                page: 2,
            ));
        });
    });
});
