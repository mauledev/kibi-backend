<?php

namespace App\Modules\Treasury\Application\UseCases\RejectPayment;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

final class RejectPaymentUseCase
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * @throws PaymentNotFoundException
     * @throws InvalidPaymentTransitionException
     */
    public function execute(RejectPaymentInput $input): Payment
    {
        $payment = $this->repository->findByUuid($input->uuid);

        if ($payment === null) {
            throw PaymentNotFoundException::withUuid($input->uuid);
        }

        // Domain guard — throws InvalidPaymentTransitionException if not Pending.
        $payment->reject();

        $updated = $this->repository->commitTransition(
            payment: $payment,
            event: PaymentStateEvent::Rejected,
            fromStatus: PaymentStatus::Pending,
            actorUserId: $input->actorUserId,
            actorName: $input->actorName,
            reason: $input->reason,
            note: $input->note,
        );

        $this->audit->log(
            action: 'payment.reject',
            userId: $input->actorUserId,
            entityId: $updated->getId(),
            schoolId: $updated->getSchoolId(),
            structBefore: ['status' => PaymentStatus::Pending->value],
            structAfter: [
                'uuid' => $updated->getUuid(),
                'status' => PaymentStatus::Rejected->value,
                'reason' => $input->reason->value,
            ],
        );

        return $updated;
    }
}
