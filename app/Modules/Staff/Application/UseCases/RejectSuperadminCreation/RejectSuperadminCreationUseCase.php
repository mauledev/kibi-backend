<?php

namespace App\Modules\Staff\Application\UseCases\RejectSuperadminCreation;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestExpiredException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotFoundException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotPendingException;
use App\Modules\Staff\Domain\Exceptions\SelfApprovalForbiddenException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Negative resolution of a superadmin creation request. Dual control still
 * applies (the proposer cannot reject their own request), but NO fresh TOTP is
 * required: the spec only demands step-up auth for approval, where an account
 * is actually created.
 *
 * @throws ApprovalRequestNotFoundException
 * @throws ApprovalRequestNotPendingException
 * @throws ApprovalRequestExpiredException
 * @throws SelfApprovalForbiddenException
 */
class RejectSuperadminCreationUseCase
{
    public function __construct(
        private readonly SuperadminApprovalRepositoryInterface $approvals,
        private readonly AuditLoggerInterface $audit,
    ) {}

    public function execute(RejectSuperadminCreationInput $input): SuperadminApprovalRequest
    {
        $request = $this->approvals->findByUuid($input->requestUuid);

        if ($request === null) {
            throw new ApprovalRequestNotFoundException($input->requestUuid);
        }

        if (! $request->isPending()) {
            throw new ApprovalRequestNotPendingException($request->getStatus()->value);
        }

        if ($request->isExpired(new DateTimeImmutable)) {
            $this->approvals->markExpired($request->getId());

            $this->audit->log(
                action: 'superadmin_approval.expire',
                userId: $input->rejectedBy,
                entityId: $request->getId(),
                structAfter: [
                    'request_uuid' => $request->getUuid(),
                    'expired_at' => $request->getExpiresAt()->format(DateTimeInterface::ATOM),
                ],
            );

            throw new ApprovalRequestExpiredException($request->getUuid());
        }

        if ($request->getProposedBy()->getId() === $input->rejectedBy) {
            throw new SelfApprovalForbiddenException;
        }

        $rejected = DB::transaction(function () use ($input): SuperadminApprovalRequest {
            $locked = $this->approvals->findByUuidForUpdate($input->requestUuid);

            if ($locked === null || ! $locked->isPending()) {
                throw new ApprovalRequestNotPendingException(
                    $locked?->getStatus()->value ?? 'unknown'
                );
            }

            $this->approvals->markRejected($locked->getId(), $input->rejectedBy, $input->reason);

            $this->audit->log(
                action: 'superadmin_approval.reject',
                userId: $input->rejectedBy,
                entityId: $locked->getId(),
                structAfter: [
                    'uuid' => $locked->getUuid(),
                    'reason' => $input->reason,
                    'proposed_by_uuid' => $locked->getProposedBy()->getUuid(),
                    'rejected_by_uuid' => $input->rejectedByUuid,
                ],
            );

            return $this->approvals->findByUuid($input->requestUuid)
                ?? throw new ApprovalRequestNotFoundException($input->requestUuid);
        });

        // TODO(security): notify the proposer of the rejection. Out of scope for slice 1.

        return $rejected;
    }
}
