<?php

use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use App\Modules\Auth\Domain\Contracts\PolicyAcceptanceRepositoryInterface;

/** In-memory fake controlling whether the user has already accepted. */
function fakeAcceptanceRepo(bool $accepted): PolicyAcceptanceRepositoryInterface
{
    return new class($accepted) implements PolicyAcceptanceRepositoryInterface
    {
        public function __construct(private bool $accepted) {}

        public function hasAccepted(int $userId, string $policyType, string $version): bool
        {
            return $this->accepted;
        }

        public function record(int $userId, string $policyType, string $version, ?string $ip): void {}
    };
}

it('requires acceptance for a required role that has not accepted', function () {
    $checker = new PolicyAcceptanceChecker(fakeAcceptanceRepo(false), '1.0', ['superadmin']);

    expect($checker->mustAccept(1, ['superadmin']))->toBeTrue();
});

it('does not require acceptance once the version is accepted', function () {
    $checker = new PolicyAcceptanceChecker(fakeAcceptanceRepo(true), '1.0', ['superadmin']);

    expect($checker->mustAccept(1, ['superadmin']))->toBeFalse();
});

it('never requires acceptance for a non-required role', function () {
    $checker = new PolicyAcceptanceChecker(fakeAcceptanceRepo(false), '1.0', ['superadmin']);

    expect($checker->mustAccept(1, ['operator', 'leader']))->toBeFalse();
});
