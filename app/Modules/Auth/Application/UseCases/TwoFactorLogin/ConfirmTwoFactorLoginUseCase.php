<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactorLogin;

use App\Modules\Auth\Application\DTOs\TwoFactorLoginResult;
use App\Modules\Auth\Application\UseCases\StaffLogin\IssueStaffSessionUseCase;
use App\Modules\Auth\Application\UseCases\TwoFactor\ConfirmTwoFactorUseCase;
use App\Modules\Auth\Domain\Contracts\TwoFactorChallengeRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorChallengeException;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Auth\Domain\Exceptions\TwoFactorNotEnrolledException;

/**
 * Completes first-login 2FA enrollment: confirms the TOTP code against the
 * pending secret, returns the recovery codes (shown once) and issues the staff
 * session, consuming the login challenge.
 */
class ConfirmTwoFactorLoginUseCase
{
    public function __construct(
        private readonly TwoFactorChallengeRepositoryInterface $challenges,
        private readonly ConfirmTwoFactorUseCase $confirm,
        private readonly IssueStaffSessionUseCase $issueSession,
    ) {}

    /**
     * @throws InvalidTwoFactorChallengeException
     * @throws TwoFactorNotEnrolledException
     * @throws InvalidTwoFactorCodeException
     */
    public function execute(string $challengeToken, string $code): TwoFactorLoginResult
    {
        $userId = $this->challenges->resolve($challengeToken);

        if ($userId === null) {
            throw new InvalidTwoFactorChallengeException;
        }

        $recoveryCodes = $this->confirm->execute($userId, $code);
        $session = $this->issueSession->execute($userId);

        $this->challenges->invalidate($challengeToken);

        return new TwoFactorLoginResult($session, $recoveryCodes);
    }
}
