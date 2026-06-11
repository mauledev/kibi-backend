<?php

use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\InvalidPaymentTransitionException;

function makePayment(PaymentStatus $status = PaymentStatus::Pending, ?int $receivedAmountCents = null): Payment
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
        payerName: 'Juan Pérez',
        reference: 'REF-001',
        amountCents: 150_000,
        receivedAmountCents: $receivedAmountCents,
        currency: 'MXN',
        paidAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        createdAt: new DateTimeImmutable('2026-06-01T09:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-06-01T09:00:00+00:00'),
    );
}

describe('Payment entity', function () {
    describe('status predicates', function () {
        it('isPending returns true only when status is Pending', function () {
            expect(makePayment(PaymentStatus::Pending)->isPending())->toBeTrue();
            expect(makePayment(PaymentStatus::Approved)->isPending())->toBeFalse();
            expect(makePayment(PaymentStatus::Rejected)->isPending())->toBeFalse();
        });

        it('isApproved returns true only when status is Approved', function () {
            expect(makePayment(PaymentStatus::Approved)->isApproved())->toBeTrue();
            expect(makePayment(PaymentStatus::Pending)->isApproved())->toBeFalse();
        });

        it('isRejected returns true only when status is Rejected', function () {
            expect(makePayment(PaymentStatus::Rejected)->isRejected())->toBeTrue();
            expect(makePayment(PaymentStatus::Pending)->isRejected())->toBeFalse();
        });
    });

    describe('approve()', function () {
        it('transitions a pending payment to Approved', function () {
            $payment = makePayment(PaymentStatus::Pending);
            $payment->approve(150_000);

            expect($payment->getStatus())->toBe(PaymentStatus::Approved);
            expect($payment->getReceivedAmountCents())->toBe(150_000);
        });

        it('updates the updatedAt timestamp', function () {
            $payment = makePayment(PaymentStatus::Pending);
            $before = $payment->getUpdatedAt();

            usleep(1_000);
            $payment->approve(150_000);

            expect($payment->getUpdatedAt())->not->toEqual($before);
        });

        it('accepts a received amount different from the nominal amount', function () {
            $payment = makePayment(PaymentStatus::Pending);
            $payment->approve(148_000);

            expect($payment->getReceivedAmountCents())->toBe(148_000);
        });

        it('throws when called on an Approved payment', function () {
            $payment = makePayment(PaymentStatus::Approved, receivedAmountCents: 150_000);

            expect(fn () => $payment->approve(150_000))
                ->toThrow(InvalidPaymentTransitionException::class, "status 'approved'");
        });

        it('throws when called on a Rejected payment', function () {
            $payment = makePayment(PaymentStatus::Rejected);

            expect(fn () => $payment->approve(150_000))
                ->toThrow(InvalidPaymentTransitionException::class, "status 'rejected'");
        });

        it('throws when called on a WithObservation payment', function () {
            $payment = makePayment(PaymentStatus::WithObservation);

            expect(fn () => $payment->approve(150_000))
                ->toThrow(InvalidPaymentTransitionException::class);
        });
    });

    describe('reject()', function () {
        it('transitions a pending payment to Rejected', function () {
            $payment = makePayment(PaymentStatus::Pending);
            $payment->reject();

            expect($payment->getStatus())->toBe(PaymentStatus::Rejected);
        });

        it('does not touch receivedAmountCents on rejection', function () {
            $payment = makePayment(PaymentStatus::Pending);
            $payment->reject();

            expect($payment->getReceivedAmountCents())->toBeNull();
        });

        it('throws when called on an Approved payment', function () {
            $payment = makePayment(PaymentStatus::Approved, receivedAmountCents: 150_000);

            expect(fn () => $payment->reject())
                ->toThrow(InvalidPaymentTransitionException::class, "status 'approved'");
        });

        it('throws when called on a Rejected payment (no double-reject)', function () {
            $payment = makePayment(PaymentStatus::Rejected);

            expect(fn () => $payment->reject())
                ->toThrow(InvalidPaymentTransitionException::class, "status 'rejected'");
        });
    });
});
