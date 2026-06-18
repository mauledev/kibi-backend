<?php

namespace App\Modules\Staff\Application\UseCases\ApproveSuperadminCreation;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestExpiredException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotFoundException;
use App\Modules\Staff\Domain\Exceptions\ApprovalRequestNotPendingException;
use App\Modules\Staff\Domain\Exceptions\ApproverNotTwoFactorEnrolledException;
use App\Modules\Staff\Domain\Exceptions\SelfApprovalForbiddenException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

use function Illuminate\Support\defer;

/**
 * Second half of the superadmin dual-control ceremony: a DIFFERENT
 * Superadmin confirms the proposal with a fresh TOTP, and only then the
 * candidate user is materialized (pending, no password), assigned the
 * 'superadmin' role and emailed a signed activation link.
 *
 * The superadmin role requires_2fa, so the existing activation/login gates force
 * the new account through password setup + 2FA enrollment on first login.
 *
 * Check order is deliberate: request state and dual-control are settled before
 * any TOTP is consumed, so a doomed approval never burns the approver's code.
 *
 * @throws ApprovalRequestNotFoundException When the uuid does not exist.
 * @throws ApprovalRequestNotPendingException When the request was already resolved.
 * @throws ApprovalRequestExpiredException When the request expired (row is transitioned).
 * @throws SelfApprovalForbiddenException When the approver is the proposer.
 * @throws ApproverNotTwoFactorEnrolledException When the approver has no confirmed 2FA.
 * @throws InvalidTwoFactorCodeException When the TOTP does not verify.
 * @throws StaffEmailAlreadyTakenException When the candidate email was taken since the proposal.
 * @throws StaffRoleNotFoundException When the 'superadmin' role is not seeded.
 */
class ApproveSuperadminCreationUseCase
{
    private const SUPERADMIN_ROLE_SLUG = 'superadmin';

    public function __construct(
        private readonly SuperadminApprovalRepositoryInterface $approvals,
        private readonly GlobalUserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly TwoFactorRepositoryInterface $twoFactorState,
        private readonly TwoFactorServiceInterface $twoFactor,
        private readonly MailerInterface $mailer,
        private readonly AuditLoggerInterface $audit,
    ) {}

    public function execute(ApproveSuperadminCreationInput $input): SuperadminApprovalRequest
    {
        $request = $this->approvals->findByUuid($input->requestUuid);

        if ($request === null) {
            throw new ApprovalRequestNotFoundException($input->requestUuid);
        }

        if (! $request->isPending()) {
            throw new ApprovalRequestNotPendingException($request->getStatus()->value);
        }

        if ($request->isExpired(new DateTimeImmutable)) {
            $this->expire($request, $input->approvedBy);

            throw new ApprovalRequestExpiredException($request->getUuid());
        }

        // Dual control — the proposer can never resolve their own request.
        if ($request->getProposedBy()->getId() === $input->approvedBy) {
            throw new SelfApprovalForbiddenException;
        }

        // Step-up auth: the approver signs the decision with a fresh TOTP. The secret
        // is read through the repository (encrypted cast), never straight from the DB.
        $secret = $this->twoFactorState->getSecret($input->approvedBy);

        if ($secret === null || ! $this->twoFactorState->isConfirmed($input->approvedBy)) {
            throw new ApproverNotTwoFactorEnrolledException;
        }

        if (! $this->twoFactor->verify($secret, $input->code)) {
            throw new InvalidTwoFactorCodeException;
        }

        // The email may have been taken between proposal and approval; the request
        // stays pending so the operator can reject it with an explicit reason.
        if ($this->users->existsByEmail($request->getCandidateEmail())) {
            throw new StaffEmailAlreadyTakenException($request->getCandidateEmail());
        }

        [$approved, $userUuid, $userEmail] = DB::transaction(function () use ($input): array {
            $locked = $this->approvals->findByUuidForUpdate($input->requestUuid);

            // A concurrent resolver may have won the row lock first.
            if ($locked === null || ! $locked->isPending()) {
                throw new ApprovalRequestNotPendingException(
                    $locked?->getStatus()->value ?? 'unknown'
                );
            }

            $user = $this->users->createPendingStaff(
                email: $locked->getCandidateEmail(),
                firstName: $locked->getCandidateFirstName(),
                lastNamePaternal: $locked->getCandidateLastNamePaternal(),
                lastNameMaternal: $locked->getCandidateLastNameMaternal(),
                phone: $locked->getCandidatePhone(),
            );

            $role = $this->roles->findBySlug(self::SUPERADMIN_ROLE_SLUG);

            if ($role === null) {
                throw new StaffRoleNotFoundException(self::SUPERADMIN_ROLE_SLUG);
            }

            // Superadmin has no permission catalogue (authority is the role itself),
            // so unlike CreatePersonnel there is no denial loop.
            $this->assignments->create(
                userId: $user->getId(),
                roleId: $role->getId(),
                schoolId: null,
                assignedBy: $input->approvedBy,
            );

            $this->approvals->markApproved($locked->getId(), $input->approvedBy, $user->getId());

            // CRITICAL audit row, atomic with the creation. Carries BOTH signatures
            // (proposer + approver) — the dual-control evidence required by the AC.
            $this->audit->log(
                action: 'superadmin.create',
                userId: $input->approvedBy,
                entityId: $user->getId(),
                structAfter: [
                    'severity' => 'CRITICAL',
                    'uuid' => $user->getUuid(),
                    'request_uuid' => $locked->getUuid(),
                    'proposed_by_uuid' => $locked->getProposedBy()->getUuid(),
                    'approved_by_uuid' => $input->approvedByUuid,
                    'candidate_email' => $locked->getCandidateEmail(),
                    'role' => self::SUPERADMIN_ROLE_SLUG,
                ],
            );

            $fresh = $this->approvals->findByUuid($input->requestUuid);

            return [$fresh, $user->getUuid(), $user->getEmail()];
        });

        // TODO(security): cross-channel alert on superadmin creation (email to TL and
        // CSO + Slack #kibi-security-critical). Out of scope for slice 1 —
        // the CRITICAL audit row above is the durable record.

        // Send the activation email AFTER the HTTP response is flushed (same rationale
        // as CreatePersonnel: keep the request fast, avoid duplicate-on-retry).
        defer(fn () => $this->sendActivationEmail($userUuid, $userEmail));

        return $approved;
    }

    private function expire(SuperadminApprovalRequest $request, int $actorId): void
    {
        $this->approvals->markExpired($request->getId());

        $this->audit->log(
            action: 'superadmin_approval.expire',
            userId: $actorId,
            entityId: $request->getId(),
            structAfter: [
                'request_uuid' => $request->getUuid(),
                'expired_at' => $request->getExpiresAt()->format(DateTimeInterface::ATOM),
            ],
        );
    }

    /**
     * Email the candidate a signed activation (magic) link pointing at the staff
     * frontend host. Same mechanism as CreatePersonnel, but with a 48h window —
     * a total-access account should not keep a week-long activation link alive.
     */
    private function sendActivationEmail(string $userUuid, string $email): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(48),
            ['user' => $userUuid],
            absolute: false,
        );

        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $baseUrl = config('app.frontend_url') ?? config('app.url');
        $baseUrl = str_replace('{APP_TENANT}', 'staff', $baseUrl);
        $baseUrl = rtrim($baseUrl, '/');

        $frontendUrl = $baseUrl.'/auth/magic';
        $activationUrl = $query ? "{$frontendUrl}?{$query}" : $frontendUrl;

        $this->mailer->sendActivation(to: $email, activationUrl: $activationUrl);
    }
}
