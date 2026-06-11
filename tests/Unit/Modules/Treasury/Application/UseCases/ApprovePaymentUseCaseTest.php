<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Treasury\Application\UseCases\ApprovePayment\ApprovePaymentInput;
use App\Modules\Treasury\Application\UseCases\ApprovePayment\ApprovePaymentUseCase;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentStateEvent;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

function makeApprovePaymentEntity(PaymentStatus $status = PaymentStatus::Pending): Payment
{
    return new Payment(
        id: 1,
        uuid: 'uuid-1',
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

describe('ApprovePaymentUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(PaymentRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new ApprovePaymentUseCase($this->repo, $this->audit);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('throws PaymentNotFoundException when the UUID is unknown', function () {
        $this->repo->shouldReceive('findByUuid')->with('missing')->andReturn(null);

        expect(fn () => $this->useCase->execute(new ApprovePaymentInput(
            uuid: 'missing',
            actorUserId: 1,
            actorName: 'Ana López',
            receivedAmountCents: 100,
            note: null,
        )))->toThrow(PaymentNotFoundException::class);
    });

    it('throws InvalidPaymentTransitionException when the payment is already approved', function () {
        $approved = makeApprovePaymentEntity(PaymentStatus::Approved);
        $this->repo->shouldReceive('findByUuid')->andReturn($approved);

        expect(fn () => $this->useCase->execute(new ApprovePaymentInput(
            uuid: 'uuid-1',
            actorUserId: 1,
            actorName: 'Ana López',
            receivedAmountCents: 100,
            note: null,
        )))->toThrow(InvalidPaymentTransitionException::class);
    });

    it('throws InvalidPaymentTransitionException when the payment is already rejected', function () {
        $rejected = makeApprovePaymentEntity(PaymentStatus::Rejected);
        $this->repo->shouldReceive('findByUuid')->andReturn($rejected);

        expect(fn () => $this->useCase->execute(new ApprovePaymentInput(
            uuid: 'uuid-1',
            actorUserId: 1,
            actorName: 'Ana López',
            receivedAmountCents: 100,
            note: null,
        )))->toThrow(InvalidPaymentTransitionException::class);
    });

    it('mutates via approve(), commits the transition atomically and writes the audit log', function () {
        $pending = makeApprovePaymentEntity(PaymentStatus::Pending);
        $persisted = makeApprovePaymentEntity(PaymentStatus::Approved);

        $this->repo->shouldReceive('findByUuid')->with('uuid-1')->andReturn($pending);

        $this->repo->shouldReceive('commitTransition')
            ->once()
            ->withArgs(function (
                $payment,
                PaymentStateEvent $event,
                ?PaymentStatus $fromStatus,
                int $actorUserId,
                string $actorName,
                $reason,
                ?string $note,
            ) {
                return $payment->getStatus() === PaymentStatus::Approved
                    && $event === PaymentStateEvent::Approved
                    && $fromStatus === PaymentStatus::Pending
                    && $actorUserId === 999
                    && $actorName === 'Ana López (Tesorera)'
                    && $reason === null
                    && $note === 'OK';
            })
            ->andReturn($persisted);

        $this->audit->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, int $userId, int $entityId): bool {
                return $action === 'payment.approve' && $userId === 999 && $entityId === 1;
            });

        $result = $this->useCase->execute(new ApprovePaymentInput(
            uuid: 'uuid-1',
            actorUserId: 999,
            actorName: 'Ana López (Tesorera)',
            receivedAmountCents: 150_000,
            note: 'OK',
        ));

        expect($result)->toBe($persisted);
    });
});
