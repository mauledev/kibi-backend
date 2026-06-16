<?php

namespace App\Modules\Auth\Application\UseCases\StaffLogin;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\DTOs\TwoFactorChallenge;
use App\Modules\Auth\Application\Support\DummyPasswordHash;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class StaffLoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roles,
        private readonly IssueStaffSessionUseCase $issueSession,
        private readonly TwoFactorRepositoryInterface $twoFactor,
        private readonly TwoFactorChallengeRepositoryInterface $challenges,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Authenticate a staff member.
     *
     * Returns a {@see LoginOutput} when the session is fully issued, or a
     * {@see TwoFactorChallenge} when credentials are valid but the role requires
     * a second factor (the client must then complete the 2FA step).
     *
     * @throws InvalidCredentialsException
     */
    public function execute(LoginInput $input): LoginOutput|TwoFactorChallenge
    {
        $user = $this->userRepository->findByEmail($input->email);

        $hash = $user?->getPasswordHash();

        // Always run exactly one bcrypt verification — even when the user does not exist
        // or has no password (OAuth-only) — so response timing does not reveal whether
        // the email is registered (anti-enumeration). Keep this hoisted outside the if:
        // inlining it in the || chain would let short-circuiting skip it and reopen the oracle.
        $passwordMatches = Hash::check($input->password, $hash ?? DummyPasswordHash::BCRYPT);

        if ($user === null || $hash === null || ! $passwordMatches) {
            $this->logFailed($input, $user?->getId());

            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            $this->logFailed($input, $user->getId(), reason: 'inactive');

            throw new InvalidCredentialsException;
        }

        if (! $user->isStaff()) {
            $this->logFailed($input, $user->getId(), reason: 'not_staff');

            throw new InvalidCredentialsException;
        }

        $userId = $user->getId();

        if ($this->requiresTwoFactor($userId)) {
            $status = $this->twoFactor->isConfirmed($userId) ? 'required' : 'setup_required';

            return new TwoFactorChallenge(
                status: $status,
                challengeToken: $this->challenges->issue($userId),
            );
        }

        // Session issuance (token + LoginOutput + must_accept_policy + the success
        // `auth.login` audit) lives in IssueStaffSessionUseCase — the single
        // session-minting point, shared with the 2FA completion endpoints.
        return $this->issueSession->execute($userId);
    }

    private function logFailed(LoginInput $input, ?int $userId, ?string $reason = null): void
    {
        $struct = ['email' => $input->email, 'ip' => $input->ip];

        if ($reason !== null) {
            $struct['reason'] = $reason;
        }

        $this->audit->log(
            action: 'auth.login_failed',
            userId: $userId,
            tenantId: $input->tenantId,
            structAfter: $struct,
        );
    }

    /**
     * A login needs a second factor when the user already has 2FA enabled
     * (opt-in or previously enrolled) OR any of their active roles mandates it
     * (the `roles.requires_2fa` flag — single source of truth).
     */
    private function requiresTwoFactor(int $userId): bool
    {
        if ($this->twoFactor->isConfirmed($userId)) {
            return true;
        }

        foreach ($this->roles->findActiveRolesForUser($userId) as $role) {
            if ($role->requiresTwoFactor()) {
                return true;
            }
        }

        return false;
    }
}
