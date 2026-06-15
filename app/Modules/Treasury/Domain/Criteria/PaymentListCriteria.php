<?php

namespace App\Modules\Treasury\Domain\Criteria;

use App\Modules\Treasury\Domain\Enums\PaymentStatus;
use DateTimeImmutable;

/**
 * Criteria object accepted by PaymentRepository::findAll.
 *
 * Wrapping the query parameters keeps the repository contract stable as new
 * filters (extra status combinations, currency, payer subset, etc.) are
 * added. All fields are optional; `tenantId` and `schoolId` carry the
 * *internal* ids after the controller resolved them from public UUIDs, in
 * line with the project's "no internal ids on the wire" rule.
 *
 * The repository does **not** apply a tenant scope automatically — staff
 * (Superadmin / Treasury operator) endpoints operate cross-tenant. Filtering
 * to a single company is opt-in via `tenantId`.
 *
 * Page size is fixed at the repository implementation (25) for MVP; if the
 * frontend later needs `?per_page`, expose a typed field here and validate
 * in the FormRequest.
 */
final readonly class PaymentListCriteria
{
    public function __construct(
        public ?PaymentStatus $status = null,
        public ?int $tenantId = null,
        public ?int $schoolId = null,
        public ?string $search = null,
        public ?DateTimeImmutable $dateFrom = null,
        public ?DateTimeImmutable $dateTo = null,
        public int $page = 1,
    ) {}
}
