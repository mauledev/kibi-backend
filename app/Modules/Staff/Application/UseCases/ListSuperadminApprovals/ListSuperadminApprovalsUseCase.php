<?php

namespace App\Modules\Staff\Application\UseCases\ListSuperadminApprovals;

use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Enums\SuperadminApprovalStatusEnum;

/**
 * Paginated queue of superadmin approval requests. Read-only: rows past their
 * expiry are reported as expired by the Resource (getEffectiveStatus) without
 * being written.
 */
class ListSuperadminApprovalsUseCase
{
    public function __construct(
        private readonly SuperadminApprovalRepositoryInterface $approvals,
    ) {}

    /**
     * @return array{items: array<SuperadminApprovalRequest>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function execute(int $page = 1, int $perPage = 20, ?SuperadminApprovalStatusEnum $status = null): array
    {
        return $this->approvals->list($page, $perPage, $status);
    }
}
