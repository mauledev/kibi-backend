<?php

namespace App\Modules\Staff\Application\UseCases\GetSuperadminApproval;

use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotFoundException;

class GetSuperadminApprovalUseCase
{
    public function __construct(
        private readonly SuperadminApprovalRepositoryInterface $approvals,
    ) {}

    /**
     * @throws ApprovalRequestNotFoundException
     */
    public function execute(string $uuid): SuperadminApprovalRequest
    {
        return $this->approvals->findByUuid($uuid)
            ?? throw new ApprovalRequestNotFoundException($uuid);
    }
}
