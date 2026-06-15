<?php

namespace App\Modules\Treasury\Domain\Criteria;

use App\Modules\Treasury\Domain\Entities\Payment;

/**
 * Result of a paginated payment list query.
 *
 * Lives in Domain (next to the Criteria it pairs with) so the repository
 * contract can return a structured pagination response without depending
 * on Laravel's framework-specific paginator types.
 */
final readonly class PaymentListResult
{
    /**
     * @param  array<Payment>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
