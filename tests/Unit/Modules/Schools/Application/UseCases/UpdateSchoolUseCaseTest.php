<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Schools\Application\UseCases\UpdateSchool\UpdateSchoolInput;
use App\Modules\Schools\Application\UseCases\UpdateSchool\UpdateSchoolUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

describe('UpdateSchoolUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new UpdateSchoolUseCase($this->repo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeUpdatableSchoolEntity(array $overrides = []): School
    {
        $pick = fn (string $key, mixed $default) => array_key_exists($key, $overrides) ? $overrides[$key] : $default;

        return new School(
            id: $pick('id', 1),
            uuid: $pick('uuid', 'uuid-1'),
            tenantId: 10,
            name: $pick('name', 'Original Name'),
            slug: $pick('slug', 'original-slug'),
            address: $pick('address', ['street' => 'Old Street']),
            phone: $pick('phone', '+52 55 0000 0000'),
            status: 'active',
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: $pick('deletedAt', null),
        );
    }

    it('throws SchoolNotFoundException when uuid does not resolve', function () {
        $this->repo->shouldReceive('findByUuid')->once()->andReturn(null);
        $this->repo->shouldNotReceive('update');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'missing',
        )))->toThrow(SchoolNotFoundException::class);
    });

    it('throws SchoolNotFoundException when entity is soft-deleted', function () {
        $school = makeUpdatableSchoolEntity([
            'deletedAt' => new DateTimeImmutable('2024-06-01'),
        ]);

        $this->repo->shouldReceive('findByUuid')->once()->andReturn($school);
        $this->repo->shouldNotReceive('update');

        expect(fn () => $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
        )))->toThrow(SchoolNotFoundException::class);
    });

    it('applies name change when hasName is true', function () {
        $school = makeUpdatableSchoolEntity();

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getName() === 'New Name'))
            ->andReturn(makeUpdatableSchoolEntity(['name' => 'New Name']));
        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasName: true,
            name: 'New Name',
        ));

        expect($result->getName())->toBe('New Name');
    });

    it('does not modify name when hasName is false', function () {
        $school = makeUpdatableSchoolEntity();

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getName() === 'Original Name'))
            ->andReturn($school);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasName: false,
            name: 'Should Be Ignored',
        ));
    });

    it('clears phone when hasPhone is true and phone is null', function () {
        $school = makeUpdatableSchoolEntity(['phone' => '+52 55 1234 5678']);

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getPhone() === null))
            ->andReturn(makeUpdatableSchoolEntity(['phone' => null]));
        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasPhone: true,
            phone: null,
        ));

        expect($result->getPhone())->toBeNull();
    });

    it('preserves phone when hasPhone is false', function () {
        $school = makeUpdatableSchoolEntity(['phone' => '+52 55 1111 1111']);

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getPhone() === '+52 55 1111 1111'))
            ->andReturn($school);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasPhone: false,
        ));
    });

    it('applies address change when hasAddress is true', function () {
        $school = makeUpdatableSchoolEntity();
        $newAddress = ['street' => 'New Street', 'state' => 'CDMX'];

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getAddress() === $newAddress))
            ->andReturn(makeUpdatableSchoolEntity(['address' => $newAddress]));
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasAddress: true,
            address: $newAddress,
        ));
    });

    it('clears address when hasAddress is true and address is null', function () {
        $school = makeUpdatableSchoolEntity(['address' => ['street' => 'Old']]);

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn (School $s) => $s->getAddress() === null))
            ->andReturn(makeUpdatableSchoolEntity(['address' => null]));
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasAddress: true,
            address: null,
        ));
    });

    it('writes audit log with before and after snapshots', function () {
        $school = makeUpdatableSchoolEntity(['name' => 'Old']);
        $updated = makeUpdatableSchoolEntity(['name' => 'New']);

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')->andReturn($updated);

        $this->audit->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?int $userId, ?int $entityId, ?int $schoolId, ?array $structBefore, ?array $structAfter) {
                expect($action)->toBe('school.update');
                expect($userId)->toBe(42);
                expect($structBefore['name'])->toBe('Old');
                expect($structAfter['name'])->toBe('New');

                return true;
            });

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
            hasName: true,
            name: 'New',
        ));
    });

    it('no-op update still persists and audits (empty patch)', function () {
        $school = makeUpdatableSchoolEntity();

        $this->repo->shouldReceive('findByUuid')->andReturn($school);
        $this->repo->shouldReceive('update')->once()->andReturn($school);
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new UpdateSchoolInput(
            actorUserId: 42,
            uuid: 'uuid-1',
        ));
    });
});
