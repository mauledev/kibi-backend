<?php

namespace App\Modules\Auth\Application\UseCases\StaffLogin;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\Support\DummyPasswordHash;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Support\Facades\Hash;

class StaffLoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenServiceInterface $tokens,
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * @throws InvalidCredentialsException
     */
    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->userRepository->findByEmail($input->email);

        $hash = $user?->getPasswordHash();

        // Always run exactly one bcrypt verification — even when the user does not exist
        // or has no password (OAuth-only) — so response timing does not reveal whether
        // the email is registered (anti-enumeration). Keep this hoisted outside the if:
        // inlining it in the || chain would let short-circuiting skip it and reopen the oracle.
        $passwordMatches = Hash::check($input->password, $hash ?? DummyPasswordHash::BCRYPT);

        if ($user === null || $hash === null || ! $passwordMatches) {
            $this->logFailed($input, $user?->getId());

            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            $this->logFailed($input, $user->getId(), reason: 'inactive');

            throw new InvalidCredentialsException;
        }

        if (! $user->isStaff()) {
            $this->logFailed($input, $user->getId(), reason: 'not_staff');

            throw new InvalidCredentialsException;
        }

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        $this->audit->log(
            action: 'auth.login',
            userId: $user->getId(),
            tenantId: $input->tenantId,
            structAfter: ['ip' => $input->ip],
        );

        return new LoginOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastNamePaternal: $user->getLastNamePaternal(),
            lastNameMaternal: $user->getLastNameMaternal(),
            fullName: $user->getFullName(),
            isStaff: true,
            token: $this->tokens->generate($user->getId()),
            roles: $roles,
            permissions: $this->extractPermissionSlugs($roles),
        );
    }

    private function logFailed(LoginInput $input, ?int $userId, ?string $reason = null): void
    {
        $struct = ['email' => $input->email, 'ip' => $input->ip];

        if ($reason !== null) {
            $struct['reason'] = $reason;
        }

        $this->audit->log(
            action: 'auth.login_failed',
            userId: $userId,
            tenantId: $input->tenantId,
            structAfter: $struct,
        );
    }

    /**
     * @param  array<Role>  $roles
     * @return list<string>
     */
    private function extractPermissionSlugs(array $roles): array
    {
        $slugs = [];
        foreach ($roles as $role) {
            foreach ($role->getPermissions() as $permission) {
                $slugs[$permission->getSlug()] = true;
            }
        }

        return array_keys($slugs);
    }
}
