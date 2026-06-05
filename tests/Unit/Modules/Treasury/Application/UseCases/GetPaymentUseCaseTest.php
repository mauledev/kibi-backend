<?php

use App\Modules\Treasury\Application\UseCases\GetPayment\GetPaymentInput;
use App\Modules\Treasury\Application\UseCases\GetPayment\GetPaymentUseCase;
use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

function makeGetPaymentEntity(int $id = 1): Payment
{
    return new Payment(
        id: $id,
        uuid: 'uuid-'.$id,
        tenantId: 10,
        companyName: 'Colegio Demo S.A.',
        schoolId: 100,
        schoolName: 'Escuela Demo',
        status: PaymentStatus::Pending,
        payerName: 'Juan',
        reference: 'REF',
        amountCents: 1_000,
        receivedAmountCents: null,
        currency: 'MXN',
        paidAt: new DateTimeImmutable,
        createdAt: new DateTimeImmutable,
        updatedAt: new DateTimeImmutable,
    );
}

describe('GetPaymentUseCase', function () {
    beforeEach(function () {
        $this->repo = Mockery::mock(PaymentRepositoryInterface::class);
        $this->useCase = new GetPaymentUseCase($this->repo);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('throws PaymentNotFoundException when the UUID is unknown', function () {
        $this->repo->shouldReceive('findByUuid')->with('missing')->andReturn(null);

        expect(fn () => $this->useCase->execute(new GetPaymentInput('missing')))
            ->toThrow(PaymentNotFoundException::class, "uuid 'missing'");
    });

    it('returns the bundle (payment + state log) when found', function () {
        $payment = makeGetPaymentEntity(7);
        $this->repo->shouldReceive('findByUuid')->with('uuid-7')->andReturn($payment);
        $this->repo->shouldReceive('findStateLog')->with(7)->andReturn([]);

        $bundle = $this->useCase->execute(new GetPaymentInput('uuid-7'));

        expect($bundle['payment'])->toBe($payment);
        expect($bundle['stateLog'])->toBe([]);
    });

    it('queries the state log using the payment internal id, not the UUID', function () {
        $payment = makeGetPaymentEntity(99);
        $this->repo->shouldReceive('findByUuid')->with('uuid-99')->andReturn($payment);
        $this->repo->shouldReceive('findStateLog')->once()->with(99)->andReturn([]);

        $this->useCase->execute(new GetPaymentInput('uuid-99'));
    });
});
