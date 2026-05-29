<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Tenant\TenantContext;
use App\Modules\Schools\Application\UseCases\CreateSchool\CreateSchoolInput;
use App\Modules\Schools\Application\UseCases\CreateSchool\CreateSchoolUseCase;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolAlreadyExistsException;

describe('CreateSchoolUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(SchoolRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->context = new TenantContext(tenantId: 10);
        $this->useCase = new CreateSchoolUseCase($this->repo, $this->context, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    function makeCreatedSchoolEntity(array $overrides = []): School
    {
        return new School(
            id: $overrides['id'] ?? 7,
            uuid: $overrides['uuid'] ?? 'uuid-new',
            tenantId: $overrides['tenantId'] ?? 10,
            name: $overrides['name'] ?? 'Colegio Kibi',
            slug: $overrides['slug'] ?? 'colegio-kibi',
            address: $overrides['address'] ?? null,
            phone: $overrides['phone'] ?? null,
            status: 'active',
            createdAt: new DateTimeImmutable('2024-01-01'),
            updatedAt: new DateTimeImmutable('2024-01-01'),
            deletedAt: null,
        );
    }

    it('creates a school when slug is unique within the tenant', function () {
        $this->repo->shouldReceive('existsBySlug')->once()->with('colegio-kibi')->andReturn(false);
        $this->repo->shouldReceive('create')
            ->once()
            ->with('Colegio Kibi', 'colegio-kibi', null, null, 'active')
            ->andReturn(makeCreatedSchoolEntity());
        $this->audit->shouldReceive('log')->once();

        $result = $this->useCase->execute(new CreateSchoolInput(
            actorUserId: 42,
            name: 'Colegio Kibi',
            slug: 'colegio-kibi',
        ));

        expect($result->getSlug())->toBe('colegio-kibi');
        expect($result->isActive())->toBeTrue();
    });

    it('throws SchoolAlreadyExistsException when slug already taken in tenant', function () {
        $this->repo->shouldReceive('existsBySlug')->once()->with('taken')->andReturn(true);
        $this->repo->shouldNotReceive('create');
        $this->audit->shouldNotReceive('log');

        expect(fn () => $this->useCase->execute(new CreateSchoolInput(
            actorUserId: 42,
            name: 'Whatever',
            slug: 'taken',
        )))->toThrow(SchoolAlreadyExistsException::class);
    });

    it('forces status to active regardless of input', function () {
        $this->repo->shouldReceive('existsBySlug')->andReturn(false);
        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(fn ($n, $s, $a, $p, $status) => $status === 'active')
            ->andReturn(makeCreatedSchoolEntity());
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new CreateSchoolInput(
            actorUserId: 42,
            name: 'X',
            slug: 'x-school',
        ));
    });

    it('passes address and phone through when provided', function () {
        $address = ['street' => 'Av. Reforma', 'state' => 'CDMX'];

        $this->repo->shouldReceive('existsBySlug')->andReturn(false);
        $this->repo->shouldReceive('create')
            ->once()
            ->with('Y', 'y-school', $address, '+52 55 1234 5678', 'active')
            ->andReturn(makeCreatedSchoolEntity(['address' => $address, 'phone' => '+52 55 1234 5678']));
        $this->audit->shouldReceive('log')->once();

        $this->useCase->execute(new CreateSchoolInput(
            actorUserId: 42,
            name: 'Y',
            slug: 'y-school',
            address: $address,
            phone: '+52 55 1234 5678',
        ));
    });

    it('writes audit log on success', function () {
        $this->repo->shouldReceive('existsBySlug')->andReturn(false);
        $this->repo->shouldReceive('create')->andReturn(makeCreatedSchoolEntity(['id' => 7]));

        $this->audit->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?int $userId, ?int $entityId, ?int $schoolId, ?array $structBefore, ?array $structAfter) {
                expect($action)->toBe('school.create');
                expect($userId)->toBe(42);
                expect($entityId)->toBe(7);
                expect($structAfter)->toHaveKey('uuid');

                return true;
            });

        $this->useCase->execute(new CreateSchoolInput(
            actorUserId: 42,
            name: 'X',
            slug: 'x-school',
        ));
    });
});
