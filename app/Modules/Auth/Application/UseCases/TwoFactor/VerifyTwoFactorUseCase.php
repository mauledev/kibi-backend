<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactor;

use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;

/**
 * Verify a code for a confirmed user. Accepts either a TOTP code or a
 * single-use recovery code. This is the entry point a login flow will call.
 */
class VerifyTwoFactorUseCase
{
    public function __construct(
        private readonly TwoFactorServiceInterface $service,
        private readonly TwoFactorRepositoryInterface $repository,
    ) {}

    public function execute(int $userId, string $code): bool
    {
        if (! $this->repository->isConfirmed($userId)) {
            return false;
        }

        $secret = $this->repository->getSecret($userId);

        if ($secret !== null && $this->service->verify($secret, $code)) {
            return true;
        }

        return $this->repository->consumeRecoveryCode($userId, $code);
    }
}
