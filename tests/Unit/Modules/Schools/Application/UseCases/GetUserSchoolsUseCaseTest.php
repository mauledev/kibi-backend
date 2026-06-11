<?php

use App\Modules\Schools\Application\UseCases\GetUserSchools\GetUserSchoolsUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Criteria\SchoolListCriteria;
use App\Modules\Schools\Domain\Entities\School;

/** Build a real School entity (the class is final, so it can't be mocked). */
function userSchoolEntity(int $id): School
{
    return new School(
        id: $id,
        uuid: "school-uuid-{$id}",
        tenantId: 1,
        name: "School {$id}",
        slug: "school-{$id}",
        address: null,
        phone: null,
        status: 'active',
        createdAt: null,
        updatedAt: null,
        deletedAt: null,
    );
}

beforeEach(function () {
    $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
    $this->useCase = new GetUserSchoolsUseCase($this->repo);
});

afterEach(function () {
    Mockery::close();
});

it('returns only the schools where the user holds an active role assignment', function () {
    $all = [userSchoolEntity(1), userSchoolEntity(2), userSchoolEntity(3)];
    $this->repo->shouldReceive('findAll')->once()->with(Mockery::type(SchoolListCriteria::class))->andReturn($all);

    $result = $this->useCase->execute([2, 3]);

    expect($result)->toHaveCount(2);
    expect($result[0]->getId())->toBe(2);
    expect($result[1]->getId())->toBe(3);
});

it('returns empty (without querying) when the user has no school assignment', function () {
    $this->repo->shouldReceive('findAll')->never();

    $result = $this->useCase->execute([]);

    expect($result)->toBe([]);
});

it('ignores accessible ids that no longer match an active school', function () {
    $all = [userSchoolEntity(1), userSchoolEntity(2)];
    $this->repo->shouldReceive('findAll')->once()->andReturn($all);

    // 2 is active, 99 was revoked/removed → only 2 comes back.
    $result = $this->useCase->execute([2, 99]);

    expect($result)->toHaveCount(1);
    expect($result[0]->getId())->toBe(2);
});
