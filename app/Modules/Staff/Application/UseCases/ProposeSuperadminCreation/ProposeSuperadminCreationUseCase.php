<?php

namespace App\Modules\Staff\Application\UseCases\ProposeSuperadminCreation;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Exceptions\DuplicatePendingApprovalException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * First half of the superadmin dual-control ceremony: a Superadmin
 * proposes creating another Superadmin. Only a request row is persisted — NO
 * user is created and NO email is sent until a different Superadmin approves
 * with a fresh TOTP (ApproveSuperadminCreationUseCase).
 *
 * @throws StaffEmailAlreadyTakenException When the candidate email already belongs to a user.
 * @throws DuplicatePendingApprovalException When a live pending request exists for the candidate.
 */
class ProposeSuperadminCreationUseCase
{
    /** Pending requests not resolved within this window expire (lazily, no cron). */
    private const TTL_HOURS = 72;

    public function __construct(
        private readonly SuperadminApprovalRepositoryInterface $approvals,
        private readonly GlobalUserRepositoryInterface $users,
        private readonly AuditLoggerInterface $audit,
    ) {}

    public function execute(ProposeSuperadminCreationInput $input): SuperadminApprovalRequest
    {
        if ($this->users->existsByEmail($input->candidateEmail)) {
            throw new StaffEmailAlreadyTakenException($input->candidateEmail);
        }

        $now = new DateTimeImmutable;
        $stale = $this->approvals->findPendingByEmail($input->candidateEmail);

        if ($stale !== null) {
            if (! $stale->isExpired($now)) {
                throw new DuplicatePendingApprovalException($input->candidateEmail);
            }

            // Lazily release the partial unique index slot held by the expired
            // request; without this, re-proposing the same candidate would 409 forever.
            $this->approvals->markExpired($stale->getId());

            $this->audit->log(
                action: 'superadmin_approval.expire',
                userId: $input->proposedBy,
                entityId: $stale->getId(),
                structAfter: [
                    'request_uuid' => $stale->getUuid(),
                    'expired_at' => $stale->getExpiresAt()->format(DateTimeInterface::ATOM),
                ],
            );
        }

        try {
            $request = $this->approvals->create(
                proposedBy: $input->proposedBy,
                justification: $input->justification,
                candidateEmail: $input->candidateEmail,
                candidateFirstName: $input->candidateFirstName,
                candidateLastNamePaternal: $input->candidateLastNamePaternal,
                candidateLastNameMaternal: $input->candidateLastNameMaternal,
                candidatePhone: $input->candidatePhone,
                expiresAt: DateTimeImmutable::createFromInterface(now()->addHours(self::TTL_HOURS)),
            );
        } catch (UniqueConstraintViolationException) {
            // Race backstop: a concurrent proposal won the partial unique index.
            throw new DuplicatePendingApprovalException($input->candidateEmail);
        }

        $this->audit->log(
            action: 'superadmin_approval.propose',
            userId: $input->proposedBy,
            entityId: $request->getId(),
            structAfter: [
                'request_uuid' => $request->getUuid(),
                'candidate_email' => $request->getCandidateEmail(),
                'justification' => $request->getJustification(),
                'expires_at' => $request->getExpiresAt()->format(DateTimeInterface::ATOM),
            ],
        );

        // TODO(security): notify the other superadmins that a request awaits review
        // (email + Slack #kibi-security-critical). Out of scope for slice 1.

        return $request;
    }
}
