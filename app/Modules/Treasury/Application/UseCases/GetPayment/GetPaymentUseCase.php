<?php

namespace App\Modules\Treasury\Application\UseCases\GetPayment;

use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Entities\PaymentStateTransition;
use App\Modules\Treasury\Domain\Exceptions\PaymentNotFoundException;

final class GetPaymentUseCase
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
    ) {}

    /**
     * Return the payment plus its state log.
     *
     * @return array{
     *     payment: Payment,
     *     stateLog: array<PaymentStateTransition>,
     * }
     *
     * @throws PaymentNotFoundException
     */
    public function execute(GetPaymentInput $input): array
    {
        $payment = $this->repository->findByUuid($input->uuid);

        if ($payment === null) {
            throw PaymentNotFoundException::withUuid($input->uuid);
        }

        return [
            'payment' => $payment,
            'stateLog' => $this->repository->findStateLog($payment->getId()),
        ];
    }
}
