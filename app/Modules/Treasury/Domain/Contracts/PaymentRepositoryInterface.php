<?php

namespace App\Modules\Treasury\Domain\Contracts;

use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;
use App\Modules\Treasury\Domain\Criteria\PaymentListResult;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Entities\PaymentStateTransition;
use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;

interface PaymentRepositoryInterface
{
    /**
     * Return a paginated slice of payments matching the given criteria.
     *
     * No tenant scope is applied automatically — staff endpoints operate
     * cross-tenant. Use `PaymentListCriteria::$tenantId` to restrict the
     * result set to a single company when needed.
     */
    public function findAll(PaymentListCriteria $criteria): PaymentListResult;

    /**
     * Find a single payment by its public UUID. Returns null when not found.
     * No tenant scope is applied — this is a staff-facing read.
     */
    public function findByUuid(string $uuid): ?Payment;

    /**
     * Resolve a school's public UUID to its internal id (cross-tenant).
     * Returns null when no school matches the given UUID.
     *
     * The controller calls this when translating the `?school_id` filter
     * before constructing the criteria.
     */
    public function resolveSchoolUuidToId(string $schoolUuid): ?int;

    /**
     * Resolve a tenant (company) public UUID to its internal id.
     * Returns null when no tenant matches the given UUID.
     *
     * The controller calls this when translating the `?company_id` filter
     * before constructing the criteria.
     */
    public function resolveCompanyUuidToId(string $companyUuid): ?int;

    /**
     * Persist a mutation on a payment entity (typically performed by
     * `approve()` or `reject()`). Returns the refreshed entity.
     *
     * This method does not write a state-log entry — use
     * {@see commitTransition()} instead when you need both writes to happen
     * atomically (which is the usual case from a UseCase).
     */
    public function update(Payment $payment): Payment;

    /**
     * Append a new entry to the payment's state log.
     *
     * Standalone use is rare. Prefer {@see commitTransition()} when the log
     * entry must be paired with a state mutation.
     */
    public function appendStateTransition(
        int $paymentId,
        PaymentStateEvent $event,
        ?PaymentStatus $fromStatus,
        PaymentStatus $toStatus,
        ?int $actorUserId,
        string $actorName,
        ?PaymentRejectReason $reason,
        ?string $note,
    ): void;

    /**
     * Persist the payment mutation and the matching state-log entry
     * atomically. The UseCase calls this after mutating the entity via
     * `approve()` or `reject()` so the entity's new status, the matching
     * `to_status` and `event` all land in the DB inside one transaction.
     */
    public function commitTransition(
        Payment $payment,
        PaymentStateEvent $event,
        ?PaymentStatus $fromStatus,
        int $actorUserId,
        string $actorName,
        ?PaymentRejectReason $reason,
        ?string $note,
    ): Payment;

    /**
     * Return the full state log of a payment, ordered chronologically
     * ascending (oldest first).
     *
     * @return array<PaymentStateTransition>
     */
    public function findStateLog(int $paymentId): array;
}
