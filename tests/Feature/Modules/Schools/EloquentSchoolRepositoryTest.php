<?php

use App\Common\Tenant\TenantContext;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentSchoolRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    });

    function bindSchoolTenantContext(Tenant $tenant): void
    {
        app()->instance(TenantContext::class, new TenantContext(tenantId: $tenant->id));
    }

    function makeSchoolRepo(): SchoolRepositoryInterface
    {
        return app(SchoolRepositoryInterface::class);
    }

    describe('tenant isolation', function () {
        it('findAll returns only the schools belonging to the current tenant', function () {
            bindSchoolTenantContext($this->tenantA);

            SchoolModel::factory()->for($this->tenantA)->count(2)->create();
            SchoolModel::factory()->for($this->tenantB)->count(1)->create();

            $schools = makeSchoolRepo()->findAll();

            expect($schools)->toHaveCount(2);
        });

        it('findAll excludes schools from a different tenant', function () {
            bindSchoolTenantContext($this->tenantA);

            SchoolModel::factory()->for($this->tenantA)->create(['name' => 'School A1']);
            SchoolModel::factory()->for($this->tenantB)->create(['name' => 'School B1']);

            $schools = makeSchoolRepo()->findAll();
            $names = array_map(fn (School $s) => $s->getName(), $schools);

            expect($names)->toContain('School A1');
            expect($names)->not->toContain('School B1');
        });

        it('findAll switches correctly when the tenant context changes', function () {
            SchoolModel::factory()->for($this->tenantA)->count(2)->create();
            SchoolModel::factory()->for($this->tenantB)->count(1)->create();

            bindSchoolTenantContext($this->tenantA);
            $schoolsA = makeSchoolRepo()->findAll();

            bindSchoolTenantContext($this->tenantB);
            $schoolsB = makeSchoolRepo()->findAll();

            expect($schoolsA)->toHaveCount(2);
            expect($schoolsB)->toHaveCount(1);
        });

        it('findAll returns an empty array when the tenant has no schools', function () {
            bindSchoolTenantContext($this->tenantA);

            SchoolModel::factory()->for($this->tenantB)->count(3)->create();

            $schools = makeSchoolRepo()->findAll();

            expect($schools)->toBeArray()->toBeEmpty();
        });
    });

    describe('findByUuid', function () {
        it('returns the school when uuid exists in the current tenant', function () {
            bindSchoolTenantContext($this->tenantA);

            $model = SchoolModel::factory()->for($this->tenantA)->create(['name' => 'Target']);

            $school = makeSchoolRepo()->findByUuid($model->uuid);

            expect($school)->not->toBeNull();
            expect($school->getName())->toBe('Target');
            expect($school->getUuid())->toBe($model->uuid);
        });

        it('returns null when uuid belongs to another tenant', function () {
            bindSchoolTenantContext($this->tenantA);

            $foreign = SchoolModel::factory()->for($this->tenantB)->create();

            expect(makeSchoolRepo()->findByUuid($foreign->uuid))->toBeNull();
        });

        it('returns null when uuid does not exist', function () {
            bindSchoolTenantContext($this->tenantA);

            expect(makeSchoolRepo()->findByUuid('00000000-0000-0000-0000-000000000000'))
                ->toBeNull();
        });
    });

    describe('existsBySlug', function () {
        it('returns true when slug exists in the current tenant', function () {
            bindSchoolTenantContext($this->tenantA);
            SchoolModel::factory()->for($this->tenantA)->create(['slug' => 'taken']);

            expect(makeSchoolRepo()->existsBySlug('taken'))->toBeTrue();
        });

        it('returns false when slug only exists in another tenant', function () {
            bindSchoolTenantContext($this->tenantA);
            SchoolModel::factory()->for($this->tenantB)->create(['slug' => 'taken-elsewhere']);

            expect(makeSchoolRepo()->existsBySlug('taken-elsewhere'))->toBeFalse();
        });

        it('returns false when slug does not exist anywhere', function () {
            bindSchoolTenantContext($this->tenantA);

            expect(makeSchoolRepo()->existsBySlug('nope'))->toBeFalse();
        });
    });

    describe('create', function () {
        it('persists the school under the current tenant context', function () {
            bindSchoolTenantContext($this->tenantA);

            $school = makeSchoolRepo()->create(
                name: 'New School',
                slug: 'new-school',
                address: ['street' => 'Av. Test'],
                phone: '+52 55 0000 0000',
                status: 'active',
            );

            expect($school->getTenantId())->toBe($this->tenantA->id);
            expect($school->getName())->toBe('New School');
            expect($school->getSlug())->toBe('new-school');
            expect($school->isActive())->toBeTrue();
            expect($school->getUuid())->toBeString()->not->toBeEmpty();
        });

        it('allows the same slug across different tenants', function () {
            bindSchoolTenantContext($this->tenantA);
            makeSchoolRepo()->create(name: 'A', slug: 'shared', address: null, phone: null, status: 'active');

            bindSchoolTenantContext($this->tenantB);
            $second = makeSchoolRepo()->create(name: 'B', slug: 'shared', address: null, phone: null, status: 'active');

            expect($second->getTenantId())->toBe($this->tenantB->id);
            expect($second->getSlug())->toBe('shared');
        });
    });

    describe('update', function () {
        it('persists mutable fields and returns updated entity', function () {
            bindSchoolTenantContext($this->tenantA);

            $model = SchoolModel::factory()->for($this->tenantA)->create([
                'name' => 'Before',
                'phone' => '+52 00 00 00',
                'address' => ['street' => 'Old'],
            ]);

            $entity = makeSchoolRepo()->findByUuid($model->uuid);
            $entity->rename('After');
            $entity->updatePhone(null);
            $entity->updateAddress(['street' => 'New Street']);

            $updated = makeSchoolRepo()->update($entity);

            expect($updated->getName())->toBe('After');
            expect($updated->getPhone())->toBeNull();
            expect($updated->getAddress())->toBe(['street' => 'New Street']);

            $this->assertDatabaseHas('schools', [
                'id' => $model->id,
                'name' => 'After',
                'phone' => null,
            ]);
        });

        it('does not touch schools in another tenant', function () {
            bindSchoolTenantContext($this->tenantA);

            $foreignModel = SchoolModel::factory()->for($this->tenantB)->create([
                'name' => 'Foreign Original',
            ]);

            // Build an entity that pretends to be the foreign school
            $entity = new School(
                id: $foreignModel->id,
                uuid: $foreignModel->uuid,
                tenantId: $this->tenantB->id,
                name: 'Hacked',
                slug: $foreignModel->slug,
                address: null,
                phone: null,
                status: 'active',
                createdAt: new DateTimeImmutable,
                updatedAt: new DateTimeImmutable,
                deletedAt: null,
            );

            expect(fn () => makeSchoolRepo()->update($entity))
                ->toThrow(ModelNotFoundException::class);

            // Foreign school must remain untouched
            $this->assertDatabaseHas('schools', [
                'id' => $foreignModel->id,
                'name' => 'Foreign Original',
            ]);
        });
    });

    describe('entity mapping', function () {
        it('returns School domain entity instances', function () {
            bindSchoolTenantContext($this->tenantA);

            SchoolModel::factory()->for($this->tenantA)->create();

            $schools = makeSchoolRepo()->findAll();

            expect($schools[0])->toBeInstanceOf(School::class);
        });

        it('maps all fields from Eloquent to the domain entity', function () {
            bindSchoolTenantContext($this->tenantA);

            $model = SchoolModel::factory()->for($this->tenantA)->create([
                'name' => 'Mapped School',
                'slug' => 'mapped-school',
                'phone' => '+52 55 1234 5678',
                'status' => 'active',
            ]);

            $schools = makeSchoolRepo()->findAll();
            $school = $schools[0];

            expect($school->getName())->toBe('Mapped School');
            expect($school->getSlug())->toBe('mapped-school');
            expect($school->getPhone())->toBe('+52 55 1234 5678');
            expect($school->isActive())->toBeTrue();
            expect($school->getTenantId())->toBe($this->tenantA->id);
        });

        it('entity uuid is a non-empty string', function () {
            bindSchoolTenantContext($this->tenantA);

            SchoolModel::factory()->for($this->tenantA)->create();

            $schools = makeSchoolRepo()->findAll();

            expect($schools[0]->getUuid())->toBeString()->not->toBeEmpty();
        });
    });
});
