<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactorLogin;

use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\UseCases\StaffLogin\IssueStaffSessionUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactor\VerifyTwoFactorUseCase;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorChallengeException;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;

/**
 * Completes 2FA for an already-enrolled user: verifies a TOTP or recovery code
 * and issues the staff session, consuming the login challenge.
 */
class VerifyTwoFactorLoginUseCase
{
    public function __construct(
        private readonly TwoFactorChallengeRepositoryInterface $challenges,
        private readonly VerifyTwoFactorUseCase $verify,
        private readonly IssueStaffSessionUseCase $issueSession,
    ) {}

    /**
     * @throws InvalidTwoFactorChallengeException
     * @throws InvalidTwoFactorCodeException
     */
    public function execute(string $challengeToken, string $code): LoginOutput
    {
        $userId = $this->challenges->resolve($challengeToken);

        if ($userId === null) {
            throw new InvalidTwoFactorChallengeException;
        }

        if (! $this->verify->execute($userId, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $session = $this->issueSession->execute($userId);

        $this->challenges->invalidate($challengeToken);

        return $session;
    }
}
