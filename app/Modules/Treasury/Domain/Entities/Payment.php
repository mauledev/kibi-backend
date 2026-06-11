<?php

namespace App\Modules\Treasury\Domain\Entities;

use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use DateTimeImmutable;

/**
 * Payment domain entity.
 *
 * Represents a payment receipt awaiting (or having gone through) treasury
 * validation. The state machine lives here: `approve()` and `reject()` are
 * the only legitimate transitions from `Pending`, and they throw
 * {@see InvalidPaymentTransitionException} when invoked from any other state.
 *
 * The entity does not know about the state log — it only mutates its own
 * fields. The UseCase is responsible for persisting the matching transition
 * row on `payment_state_transitions`.
 */
final class Payment
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $tenantId,
        private readonly string $companyName,
        private readonly int $schoolId,
        private readonly string $schoolName,
        private readonly ?int $createdBy,
        private PaymentStatus $status,
        private readonly string $payerName,
        private readonly ?string $reference,
        private readonly int $amountCents,
        private ?int $receivedAmountCents,
        private readonly string $currency,
        private readonly ?DateTimeImmutable $paidAt,
        private readonly ?DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /** Returns the internal surrogate key (Infrastructure use only). */
    public function getId(): int
    {
        return $this->id;
    }

    /** Returns the public UUID used in routes and responses. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Returns the tenant (company) this payment belongs to. */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * Returns the denormalised company (tenant) name, populated by the
     * repository at read time. Surfaced in cross-tenant staff listings so
     * the Superadmin can tell which company each payment belongs to.
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /** Returns the school this payment was made for. */
    public function getSchoolId(): int
    {
        return $this->schoolId;
    }

    /**
     * Returns the denormalised school name, populated by the repository at
     * read time. This is a projection — not part of the payment's own state —
     * and exists to avoid N+1 queries when listing payments.
     */
    public function getSchoolName(): string
    {
        return $this->schoolName;
    }

    /**
     * Returns the id of the user who originally uploaded this payment, or
     * null when no creator is recorded (legacy rows or fixtures). For MVP
     * there is no Owner upload endpoint so this is typically null — the
     * column exists to preserve the data once that endpoint lands and to
     * enable a later segregation-of-duties check (creator !== approver).
     * See post-mvp.md PM-004.
     */
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    /** Returns the current lifecycle status. */
    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    /** Returns the payer's display name as captured at creation time. */
    public function getPayerName(): string
    {
        return $this->payerName;
    }

    /** Returns the bank reference / tracking string if provided. */
    public function getReference(): ?string
    {
        return $this->reference;
    }

    /** Returns the nominal amount of the payment, in integer cents. */
    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    /**
     * Returns the amount actually credited by the operator on approval, in
     * cents. Null until the payment is approved.
     */
    public function getReceivedAmountCents(): ?int
    {
        return $this->receivedAmountCents;
    }

    /** Returns the ISO 4217 currency code (e.g. MXN, USD). */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /** Returns when the payer claims to have paid, or null when unknown. */
    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    /** Returns the creation timestamp. */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Returns the last-update timestamp. */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Status predicates

    /** True when the payment is still awaiting validation. */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /** True when the payment has been approved by the operator. */
    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::Approved;
    }

    /** True when the payment has been rejected by the operator. */
    public function isRejected(): bool
    {
        return $this->status === PaymentStatus::Rejected;
    }

    // State transitions

    /**
     * Mark this payment as approved with the actually-received amount.
     *
     * @throws InvalidPaymentTransitionException when the current status is
     *                                           not Pending.
     */
    public function approve(int $receivedAmountCents): void
    {
        if (! $this->isPending()) {
            throw InvalidPaymentTransitionException::cannotApprove($this->status);
        }

        $this->status = PaymentStatus::Approved;
        $this->receivedAmountCents = $receivedAmountCents;
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * Mark this payment as rejected. The reason and free-text note are
     * captured separately on the state transition log, not on the entity.
     *
     * @throws InvalidPaymentTransitionException when the current status is
     *                                           not Pending.
     */
    public function reject(): void
    {
        if (! $this->isPending()) {
            throw InvalidPaymentTransitionException::cannotReject($this->status);
        }

        $this->status = PaymentStatus::Rejected;
        $this->updatedAt = new DateTimeImmutable;
    }
}
