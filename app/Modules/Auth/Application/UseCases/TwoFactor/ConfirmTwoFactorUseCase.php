<?php

namespace App\Modules\Auth\Application\UseCases\TwoFactor;

use App\Modules\Auth\Domain\Contracts\TwoFactorRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TwoFactorServiceInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidTwoFactorCodeException;
use App\Modules\Auth\Domain\Exceptions\TwoFactorNotEnrolledException;
use Illuminate\Support\Facades\Hash;

/**
 * Confirm a pending enrollment by verifying the first TOTP code, then generate
 * and persist (hashed) recovery codes. Returns the plain recovery codes once.
 */
class ConfirmTwoFactorUseCase
{
    public function __construct(
        private readonly TwoFactorServiceInterface $service,
        private readonly TwoFactorRepositoryInterface $repository,
    ) {}

    /**
     * @return array<string> Plain recovery codes — shown to the user only here.
     *
     * @throws TwoFactorNotEnrolledException When there is no pending secret.
     * @throws InvalidTwoFactorCodeException When the code does not match.
     */
    public function execute(int $userId, string $code): array
    {
        $secret = $this->repository->getSecret($userId);

        if ($secret === null) {
            throw new TwoFactorNotEnrolledException($userId);
        }

        if (! $this->service->verify($secret, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $recoveryCodes = $this->service->generateRecoveryCodes();

        $this->repository->confirm(
            $userId,
            array_map(fn (string $rc): string => Hash::make($rc), $recoveryCodes),
        );

        return $recoveryCodes;
    }
}
