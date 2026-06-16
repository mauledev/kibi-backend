<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactor;

use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;

/**
 * Disable two-factor for a user, clearing the secret and recovery codes.
 */
class DisableTwoFactorUseCase
{
    public function __construct(
        private readonly TwoFactorRepositoryInterface $repository,
    ) {}

    public function execute(int $userId): void
    {
        $this->repository->disable($userId);
    }
}
