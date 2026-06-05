<?php

use App\Models\Payment as PaymentModel;
use App\Models\PaymentStateTransition as PaymentStateTransitionModel;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;
use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EloquentPaymentRepository (cross-tenant)', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->schoolA = SchoolModel::factory()->forTenant($this->tenantA)->create();
        $this->schoolB = SchoolModel::factory()->forTenant($this->tenantB)->create();
    });

    function makePaymentRepo(): PaymentRepositoryInterface
    {
        return app(PaymentRepositoryInterface::class);
    }

    describe('cross-tenant reads', function () {
        it('findAll returns payments from every tenant (no tenant scope applied)', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(2)->create();
            PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->count(3)->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria);

            expect($result->total)->toBe(5);
        });

        it('items carry both school_name and company_name from the join', function () {
            $school = SchoolModel::factory()->forTenant($this->tenantA)->create(['name' => 'Escuela Específica']);
            // tenantA's name comes from the factory — read it back after creation.
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($school)->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria);

            expect($result->items[0]->getSchoolName())->toBe('Escuela Específica');
            expect($result->items[0]->getCompanyName())->toBe($this->tenantA->name);
        });

        it('findByUuid resolves any tenant\'s payment', function () {
            $foreign = PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->create();

            $entity = makePaymentRepo()->findByUuid($foreign->uuid);

            expect($entity)->not->toBeNull();
            expect($entity->getTenantId())->toBe($this->tenantB->id);
        });
    });

    describe('findAll filtering', function () {
        it('filters by tenantId when provided', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(2)->create();
            PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->count(3)->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(tenantId: $this->tenantA->id));

            expect($result->total)->toBe(2);
        });

        it('filters by status when provided', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->approved()->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(status: PaymentStatus::Approved));

            expect($result->total)->toBe(1);
        });

        it('filters by schoolId when provided', function () {
            $secondSchool = SchoolModel::factory()->forTenant($this->tenantA)->create();
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(2)->create();
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($secondSchool)->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(schoolId: $this->schoolA->id));

            expect($result->total)->toBe(2);
        });

        it('combines tenantId and status', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->approved()->create();
            PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->approved()->create();

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(
                status: PaymentStatus::Approved,
                tenantId: $this->tenantA->id,
            ));

            expect($result->total)->toBe(1);
        });

        it('search matches payer_name (ilike)', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create(['payer_name' => 'Juan Pérez']);
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create(['payer_name' => 'María López']);

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(search: 'pérez'));

            expect($result->total)->toBe(1);
        });

        it('date_from and date_to are inclusive on paid_at', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create(['paid_at' => '2026-06-01 12:00:00']);
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create(['paid_at' => '2026-05-30 12:00:00']);
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create(['paid_at' => '2026-06-30 23:00:00']);

            $result = makePaymentRepo()->findAll(new PaymentListCriteria(
                dateFrom: new DateTimeImmutable('2026-06-01'),
                dateTo: new DateTimeImmutable('2026-06-30'),
            ));

            expect($result->total)->toBe(2);
        });

        it('paginates at 25 per page', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(30)->create();

            $page1 = makePaymentRepo()->findAll(new PaymentListCriteria(page: 1));
            $page2 = makePaymentRepo()->findAll(new PaymentListCriteria(page: 2));

            expect($page1->total)->toBe(30);
            expect($page1->perPage)->toBe(25);
            expect($page1->items)->toHaveCount(25);
            expect($page2->items)->toHaveCount(5);
        });
    });

    describe('resolveSchoolUuidToId / resolveCompanyUuidToId', function () {
        it('resolves any school by UUID without tenant scope', function () {
            expect(makePaymentRepo()->resolveSchoolUuidToId($this->schoolB->uuid))->toBe($this->schoolB->id);
        });

        it('returns null when school UUID does not exist', function () {
            expect(makePaymentRepo()->resolveSchoolUuidToId('00000000-0000-0000-0000-000000000000'))->toBeNull();
        });

        it('resolves any tenant by UUID', function () {
            expect(makePaymentRepo()->resolveCompanyUuidToId($this->tenantA->uuid))->toBe($this->tenantA->id);
        });

        it('returns null when tenant UUID does not exist', function () {
            expect(makePaymentRepo()->resolveCompanyUuidToId('00000000-0000-0000-0000-000000000000'))->toBeNull();
        });
    });

    describe('update + commitTransition', function () {
        it('persists the new status and received_amount_cents on update', function () {
            $model = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create([
                'status' => 'pending',
            ]);

            $entity = makePaymentRepo()->findByUuid($model->uuid);
            $entity->approve(148_000);
            makePaymentRepo()->update($entity);

            $this->assertDatabaseHas('payments', [
                'id' => $model->id,
                'status' => 'approved',
                'received_amount_cents' => 148_000,
            ]);
        });

        it('commitTransition atomically persists the payment and the log entry', function () {
            $actor = User::factory()->staff()->create();
            $model = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();
            $entity = makePaymentRepo()->findByUuid($model->uuid);
            $entity->reject();

            $updated = makePaymentRepo()->commitTransition(
                payment: $entity,
                event: PaymentStateEvent::Rejected,
                fromStatus: PaymentStatus::Pending,
                actorUserId: $actor->id,
                actorName: 'System',
                reason: PaymentRejectReason::AmountMismatch,
                note: 'note text',
            );

            expect($updated->getStatus())->toBe(PaymentStatus::Rejected);
            $this->assertDatabaseHas('payments', ['id' => $model->id, 'status' => 'rejected']);
            $this->assertDatabaseHas('payment_state_transitions', [
                'payment_id' => $model->id,
                'event' => 'rejected',
                'reason' => 'amount_mismatch',
                'note' => 'note text',
            ]);
        });
    });

    describe('findStateLog', function () {
        it('returns entries ordered chronologically ascending', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            PaymentStateTransitionModel::factory()->forPayment($payment)->create([
                'event' => 'created',
                'created_at' => '2026-06-01 10:00:00',
            ]);
            PaymentStateTransitionModel::factory()->forPayment($payment)->approved()->create([
                'created_at' => '2026-06-02 10:00:00',
            ]);

            $log = makePaymentRepo()->findStateLog($payment->id);

            expect($log)->toHaveCount(2);
            expect($log[0]->getEvent())->toBe(PaymentStateEvent::Created);
            expect($log[1]->getEvent())->toBe(PaymentStateEvent::Approved);
        });
    });
});
