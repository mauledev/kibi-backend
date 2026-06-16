<?php

namespace App\Modules\Treasury\Domain\Entities;

use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use DateTimeImmutable;

/**
 * One append-only entry in a payment's state log.
 *
 * Captures who triggered the transition (with their display name snapshotted
 * at the time), what event it was, and optional reason/note metadata when
 * the event was a rejection or an explicit approval comment.
 */
final readonly class PaymentStateTransition
{
    public function __construct(
        private int $id,
        private int $paymentId,
        private PaymentStateEvent $event,
        private ?PaymentStatus $fromStatus,
        private PaymentStatus $toStatus,
        private ?int $actorUserId,
        private string $actorName,
        private ?PaymentRejectReason $reason,
        private ?string $note,
        private DateTimeImmutable $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function getEvent(): PaymentStateEvent
    {
        return $this->event;
    }

    public function getFromStatus(): ?PaymentStatus
    {
        return $this->fromStatus;
    }

    public function getToStatus(): PaymentStatus
    {
        return $this->toStatus;
    }

    public function getActorUserId(): ?int
    {
        return $this->actorUserId;
    }

    public function getActorName(): string
    {
        return $this->actorName;
    }

    public function getReason(): ?PaymentRejectReason
    {
        return $this->reason;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
