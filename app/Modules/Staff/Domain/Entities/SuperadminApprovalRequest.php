<?php

namespace App\Modules\Staff\Domain\Entities;

use App\Modules\Staff\Domain\Enums\SuperadminApprovalStatusEnum;
use DateTimeImmutable;

/**
 * A superadmin creation request under dual control.
 *
 * Carries an immutable snapshot of the candidate's personal data: what the
 * proposer submitted is exactly what the approver reviews and what gets
 * materialized into a user on approval.
 */
class SuperadminApprovalRequest
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly SuperadminApprovalStatusEnum $status,
        private readonly string $justification,
        private readonly string $candidateEmail,
        private readonly string $candidateFirstName,
        private readonly string $candidateLastNamePaternal,
        private readonly ?string $candidateLastNameMaternal,
        private readonly ?string $candidatePhone,
        private readonly ApprovalParticipant $proposedBy,
        private readonly ?ApprovalParticipant $resolvedBy,
        private readonly ?DateTimeImmutable $resolvedAt,
        private readonly ?string $rejectionReason,
        private readonly ?string $createdUserUuid,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getStatus(): SuperadminApprovalStatusEnum
    {
        return $this->status;
    }

    public function getJustification(): string
    {
        return $this->justification;
    }

    public function getCandidateEmail(): string
    {
        return $this->candidateEmail;
    }

    public function getCandidateFirstName(): string
    {
        return $this->candidateFirstName;
    }

    public function getCandidateLastNamePaternal(): string
    {
        return $this->candidateLastNamePaternal;
    }

    public function getCandidateLastNameMaternal(): ?string
    {
        return $this->candidateLastNameMaternal;
    }

    public function getCandidatePhone(): ?string
    {
        return $this->candidatePhone;
    }

    public function getProposedBy(): ApprovalParticipant
    {
        return $this->proposedBy;
    }

    public function getResolvedBy(): ?ApprovalParticipant
    {
        return $this->resolvedBy;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /** Uuid of the superadmin user created on approval, null until then. */
    public function getCreatedUserUuid(): ?string
    {
        return $this->createdUserUuid;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPending(): bool
    {
        return $this->status === SuperadminApprovalStatusEnum::PENDING_APPROVAL;
    }

    /** A pending request whose expiry already elapsed (stored status not yet flipped). */
    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->isPending() && $this->expiresAt < $now;
    }

    /**
     * Status as the outside world should see it: reads never write, so a pending
     * row past its expiry is reported as EXPIRED without touching the database.
     */
    public function getEffectiveStatus(DateTimeImmutable $now): SuperadminApprovalStatusEnum
    {
        return $this->isExpired($now)
            ? SuperadminApprovalStatusEnum::EXPIRED
            : $this->status;
    }
}
