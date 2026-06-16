<?php

namespace App\Modules\Treasury\Application\UseCases\ListPayments;

use App\Modules\Treasury\Domain\Contracts\PaymentRepositoryInterface;
use App\Modules\Treasury\Domain\Criteria\PaymentListResult;

final class ListPaymentsUseCase
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
    ) {}

    public function execute(ListPaymentsInput $input): PaymentListResult
    {
        return $this->repository->findAll($input->criteria);
    }
}
