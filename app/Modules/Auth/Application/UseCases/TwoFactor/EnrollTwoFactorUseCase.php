<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactor;

use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Domain\Entities\TwoFactorEnrollment;

/**
 * Start a two-factor enrollment: generate a secret, store it pending, and
 * return the secret + provisioning URI (for the QR). Does not confirm anything.
 *
 * Idempotent while pending: if the user already has an unconfirmed secret it is
 * reused instead of minting a new one. This keeps repeated setup calls safe
 * (client retries / React StrictMode double-invoke) — otherwise each call would
 * mint a fresh secret and the QR shown could race the stored pending secret.
 */
class EnrollTwoFactorUseCase
{
    public function __construct(
        private readonly TwoFactorServiceInterface $service,
        private readonly TwoFactorRepositoryInterface $repository,
    ) {}

    public function execute(int $userId, string $accountLabel, string $issuer): TwoFactorEnrollment
    {
        $existing = $this->repository->getSecret($userId);

        $secret = ($existing !== null && ! $this->repository->isConfirmed($userId))
            ? $existing
            : $this->service->generateSecret();

        $this->repository->storePendingSecret($userId, $secret);

        return new TwoFactorEnrollment(
            secret: $secret,
            provisioningUri: $this->service->provisioningUri($secret, $accountLabel, $issuer),
        );
    }
}
