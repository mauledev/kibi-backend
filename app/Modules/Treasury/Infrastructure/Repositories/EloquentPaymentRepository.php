<?php

namespace App\Modules\Treasury\Infrastructure\Repositories;

use App\Models\Payment as PaymentModel;
use App\Models\PaymentStateTransition as PaymentStateTransitionModel;
use App\Models\School as SchoolModel;
use App\Models\Tenant as TenantModel;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;
use App\Modules\Treasury\Domain\Criteria\PaymentListResult;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Entities\PaymentStateTransition;
use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cross-tenant Eloquent repository for Payments.
 *
 * Unlike the tenant modules (Schools, Users, etc.), this repository does NOT
 * scope its queries by `TenantContext` — the staff routes that consume it
 * (Superadmin / Treasury) operate on every tenant's payments. Filtering by a
 * specific tenant is opt-in via `PaymentListCriteria::$tenantId`.
 */
class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    private const PER_PAGE = 25;

    /** {@inheritDoc} */
    public function findAll(PaymentListCriteria $criteria): PaymentListResult
    {
        $query = $this->baseQuery();

        if ($criteria->status !== null) {
            $query->where('payments.status', $criteria->status->value);
        }

        if ($criteria->tenantId !== null) {
            $query->where('payments.tenant_id', $criteria->tenantId);
        }

        if ($criteria->schoolId !== null) {
            $query->where('payments.school_id', $criteria->schoolId);
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $term = '%'.$criteria->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('payments.payer_name', 'ilike', $term)
                    ->orWhere('payments.reference', 'ilike', $term);
            });
        }

        if ($criteria->dateFrom !== null) {
            $query->where('payments.paid_at', '>=', $criteria->dateFrom->format('Y-m-d 00:00:00'));
        }

        if ($criteria->dateTo !== null) {
            $query->where('payments.paid_at', '<=', $criteria->dateTo->format('Y-m-d 23:59:59'));
        }

        $page = max(1, $criteria->page);
        $paginator = $query->orderByDesc('payments.paid_at')
            ->orderByDesc('payments.id')
            ->paginate(perPage: self::PER_PAGE, page: $page);

        $items = [];
        foreach ($paginator->items() as $model) {
            $items[] = $this->toDomain($model);
        }

        return new PaymentListResult(
            items: $items,
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?Payment
    {
        $model = $this->baseQuery()
            ->where('payments.uuid', $uuid)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Base query that joins schools and tenants so every read carries the
     * denormalised names without N+1 in the Resource layer.
     *
     * @return Builder<PaymentModel>
     */
    private function baseQuery(): Builder
    {
        return PaymentModel::query()
            ->select('payments.*')
            ->selectRaw('schools.name as school_name_join')
            ->selectRaw('tenants.name as company_name_join')
            ->join('schools', 'schools.id', '=', 'payments.school_id')
            ->join('tenants', 'tenants.id', '=', 'payments.tenant_id');
    }

    /** {@inheritDoc} */
    public function resolveSchoolUuidToId(string $schoolUuid): ?int
    {
        $id = SchoolModel::query()
            ->where('uuid', $schoolUuid)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** {@inheritDoc} */
    public function resolveCompanyUuidToId(string $companyUuid): ?int
    {
        $id = TenantModel::query()
            ->where('uuid', $companyUuid)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** {@inheritDoc} */
    public function update(Payment $payment): Payment
    {
        $model = PaymentModel::query()->findOrFail($payment->getId());

        $model->update([
            'status' => $payment->getStatus()->value,
            'received_amount_cents' => $payment->getReceivedAmountCents(),
        ]);

        // Re-read via the joined query so the returned entity carries the
        // denormalised school_name and company_name needed by the Resource layer.
        $joined = $this->baseQuery()
            ->where('payments.id', $payment->getId())
            ->firstOrFail();

        return $this->toDomain($joined);
    }

    /** {@inheritDoc} */
    public function commitTransition(
        Payment $payment,
        PaymentStateEvent $event,
        ?PaymentStatus $fromStatus,
        int $actorUserId,
        string $actorName,
        ?PaymentRejectReason $reason,
        ?string $note,
    ): Payment {
        return DB::transaction(function () use ($payment, $event, $fromStatus, $actorUserId, $actorName, $reason, $note): Payment {
            $persisted = $this->update($payment);
            $this->appendStateTransition(
                paymentId: $persisted->getId(),
                event: $event,
                fromStatus: $fromStatus,
                toStatus: $persisted->getStatus(),
                actorUserId: $actorUserId,
                actorName: $actorName,
                reason: $reason,
                note: $note,
            );

            return $persisted;
        });
    }

    /** {@inheritDoc} */
    public function appendStateTransition(
        int $paymentId,
        PaymentStateEvent $event,
        ?PaymentStatus $fromStatus,
        PaymentStatus $toStatus,
        ?int $actorUserId,
        string $actorName,
        ?PaymentRejectReason $reason,
        ?string $note,
    ): void {
        PaymentStateTransitionModel::create([
            'payment_id' => $paymentId,
            'event' => $event->value,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'actor_user_id' => $actorUserId,
            'actor_name' => $actorName,
            'reason' => $reason?->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** {@inheritDoc} */
    public function findStateLog(int $paymentId): array
    {
        $models = PaymentStateTransitionModel::query()
            ->where('payment_id', $paymentId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $models->map(fn (PaymentStateTransitionModel $m) => $this->toStateTransitionDomain($m))->all();
    }

    private function toDomain(PaymentModel $model): Payment
    {
        return new Payment(
            id: $model->id,
            uuid: $model->uuid,
            tenantId: $model->tenant_id,
            companyName: (string) ($model->getAttribute('company_name_join') ?? ''),
            schoolId: $model->school_id,
            schoolName: (string) ($model->getAttribute('school_name_join') ?? ''),
            status: PaymentStatus::from($model->status),
            payerName: $model->payer_name,
            reference: $model->reference,
            amountCents: $model->amount_cents,
            receivedAmountCents: $model->received_amount_cents,
            currency: $model->currency,
            paidAt: $model->paid_at !== null
                ? new DateTimeImmutable($model->paid_at->toIso8601String())
                : null,
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
            updatedAt: new DateTimeImmutable($model->updated_at?->toIso8601String() ?? 'now'),
        );
    }

    private function toStateTransitionDomain(PaymentStateTransitionModel $model): PaymentStateTransition
    {
        return new PaymentStateTransition(
            id: $model->id,
            paymentId: $model->payment_id,
            event: PaymentStateEvent::from($model->event),
            fromStatus: $model->from_status !== null ? PaymentStatus::from($model->from_status) : null,
            toStatus: PaymentStatus::from($model->to_status),
            actorUserId: $model->actor_user_id,
            actorName: $model->actor_name,
            reason: $model->reason !== null ? PaymentRejectReason::from($model->reason) : null,
            note: $model->note,
            createdAt: new DateTimeImmutable($model->created_at->toIso8601String()),
        );
    }
}
