<?php

namespace App\Modules\Auth\Application\UseCases\StaffLogin;

use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\DTOs\TwoFactorChallenge;
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

        if (! $user || $hash === null || ! Hash::check($input->password, $hash)) {
            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            throw new InvalidCredentialsException('User is inactive');
        }

        if (! $user->isStaff()) {
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

        return $this->issueSession->execute($userId);
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
