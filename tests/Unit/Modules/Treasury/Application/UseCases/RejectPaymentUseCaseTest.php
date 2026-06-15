<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Treasury\Application\UseCases\RejectPayment\RejectPaymentInput;
use App\Modules\Treasury\Application\UseCases\RejectPayment\RejectPaymentUseCase;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentRejectReason;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

function makeRejectPaymentEntity(PaymentStatus $status = PaymentStatus::Pending): Payment
{
    return new Payment(
        id: 5,
        uuid: 'uuid-5',
        tenantId: 10,
        companyName: 'Colegio Demo S.A.',
        schoolId: 100,
        schoolName: 'Escuela Demo',
        createdBy: null,
        status: $status,
        payerName: 'Juan',
        reference: 'REF',
        amountCents: 150_000,
        receivedAmountCents: null,
        currency: 'MXN',
        paidAt: new DateTimeImmutable,
        createdAt: new DateTimeImmutable,
        updatedAt: new DateTimeImmutable,
    );
}

describe('RejectPaymentUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(PaymentRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new RejectPaymentUseCase($this->repo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('throws PaymentNotFoundException when the UUID is unknown', function () {
        $this->repo->shouldReceive('findByUuid')->andReturn(null);

        expect(fn () => $this->useCase->execute(new RejectPaymentInput(
            uuid: 'missing',
            actorUserId: 1,
            actorName: 'Ana López',
            reason: PaymentRejectReason::Other,
            note: null,
        )))->toThrow(PaymentNotFoundException::class);
    });

    it('throws InvalidPaymentTransitionException when the payment is already approved', function () {
        $this->repo->shouldReceive('findByUuid')->andReturn(makeRejectPaymentEntity(PaymentStatus::Approved));

        expect(fn () => $this->useCase->execute(new RejectPaymentInput(
            uuid: 'uuid-5',
            actorUserId: 1,
            actorName: 'Ana López',
            reason: PaymentRejectReason::AmountMismatch,
            note: null,
        )))->toThrow(InvalidPaymentTransitionException::class);
    });

    it('mutates via reject(), commits the transition atomically and writes the audit log', function () {
        $pending = makeRejectPaymentEntity(PaymentStatus::Pending);
        $persisted = makeRejectPaymentEntity(PaymentStatus::Rejected);

        $this->repo->shouldReceive('findByUuid')->andReturn($pending);

        $this->repo->shouldReceive('commitTransition')
            ->once()
            ->withArgs(function (
                $payment,
                PaymentStateEvent $event,
                ?PaymentStatus $fromStatus,
                int $actorUserId,
                string $actorName,
                ?PaymentRejectReason $reason,
                ?string $note,
            ) {
                return $payment->getStatus() === PaymentStatus::Rejected
                    && $event === PaymentStateEvent::Rejected
                    && $fromStatus === PaymentStatus::Pending
                    && $reason === PaymentRejectReason::AmountMismatch
                    && $note === 'Faltaron 200 pesos';
            })
            ->andReturn($persisted);

        $this->audit->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action): bool {
                return $action === 'payment.reject';
            });

        $result = $this->useCase->execute(new RejectPaymentInput(
            uuid: 'uuid-5',
            actorUserId: 999,
            actorName: 'Ana López (Tesorera)',
            reason: PaymentRejectReason::AmountMismatch,
            note: 'Faltaron 200 pesos',
        ));

        expect($result)->toBe($persisted);
    });
});
