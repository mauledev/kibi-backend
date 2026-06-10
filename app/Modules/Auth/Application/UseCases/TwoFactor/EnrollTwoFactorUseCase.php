<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactor;

use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Domain\Entities\TwoFactorEnrollment;

/**
 * Start a two-factor enrollment: generate a secret, store it pending, and
 * return the secret + provisioning URI (for the QR). Does not confirm anything.
 */
class EnrollTwoFactorUseCase
{
    public function __construct(
        private readonly TwoFactorServiceInterface $service,
        private readonly TwoFactorRepositoryInterface $repository,
    ) {}

    public function execute(int $userId, string $accountLabel, string $issuer): TwoFactorEnrollment
    {
        $secret = $this->service->generateSecret();

        $this->repository->storePendingSecret($userId, $secret);

        return new TwoFactorEnrollment(
            secret: $secret,
            provisioningUri: $this->service->provisioningUri($secret, $accountLabel, $issuer),
        );
    }
}
