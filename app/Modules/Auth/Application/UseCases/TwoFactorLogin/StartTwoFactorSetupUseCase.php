<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactorLogin;

use App\Modules\Auth\Application\UseCases\TwoFactor\EnrollTwoFactorUseCase;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\TwoFactorEnrollment;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorChallengeException;

/**
 * First-login 2FA enrollment: resolves the login challenge to a user, generates
 * a pending TOTP secret and returns the provisioning URI so the client can show
 * the QR. The session is NOT issued yet — the user must confirm a code first.
 */
class StartTwoFactorSetupUseCase
{
    public function __construct(
        private readonly TwoFactorChallengeRepositoryInterface $challenges,
        private readonly EnrollTwoFactorUseCase $enroll,
        private readonly TwoFactorRepositoryInterface $twoFactor,
        private readonly UserRepositoryInterface $userRepository,
        private readonly string $issuer,
    ) {}

    /**
     * @throws InvalidTwoFactorChallengeException
     */
    public function execute(string $challengeToken): TwoFactorEnrollment
    {
        $userId = $this->challenges->resolve($challengeToken);

        if ($userId === null) {
            throw new InvalidTwoFactorChallengeException;
        }

        $user = $this->userRepository->findById($userId);

        if ($user === null || $this->twoFactor->isConfirmed($userId)) {
            throw new InvalidTwoFactorChallengeException;
        }

        return $this->enroll->execute($userId, $user->getEmail(), $this->issuer);
    }
}
