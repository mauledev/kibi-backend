<?php

namespace App\Modules\Treasury\Application\UseCases\ApprovePayment;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

final class ApprovePaymentUseCase
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * @throws PaymentNotFoundException
     * @throws InvalidPaymentTransitionException
     */
    public function execute(ApprovePaymentInput $input): Payment
    {
        $payment = $this->repository->findByUuid($input->uuid);

        if ($payment === null) {
            throw PaymentNotFoundException::withUuid($input->uuid);
        }

        // Domain guard — throws InvalidPaymentTransitionException if not Pending.
        $payment->approve($input->receivedAmountCents);

        $updated = $this->repository->commitTransition(
            payment: $payment,
            event: PaymentStateEvent::Approved,
            fromStatus: PaymentStatus::Pending,
            actorUserId: $input->actorUserId,
            actorName: $input->actorName,
            reason: null,
            note: $input->note,
        );

        $this->audit->log(
            action: 'payment.approve',
            userId: $input->actorUserId,
            entityId: $updated->getId(),
            structBefore: ['status' => PaymentStatus::Pending->value, 'received_amount_cents' => null],
            structAfter: [
                'status' => PaymentStatus::Approved->value,
                'received_amount_cents' => $input->receivedAmountCents,
            ],
        );

        return $updated;
    }
}
