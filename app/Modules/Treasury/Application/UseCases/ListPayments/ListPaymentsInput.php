<?php

namespace App\Modules\Treasury\Application\UseCases\ListPayments;

use App\Modules\Treasury\Domain\Criteria\PaymentListCriteria;

/**
 * Input DTO for the ListPayments use case.
 *
 * Carries the criteria already resolved from the HTTP query string (with
 * school UUID → id translation performed by the controller).
 */
final readonly class ListPaymentsInput
{
    public function __construct(
        public PaymentListCriteria $criteria,
    ) {}
}
