<?php

namespace App\Modules\Auth\Application\UseCases\Login;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Support\Facades\Hash;

class LoginUseCase
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

        if (! $user || $hash === null || ! Hash::check($input->password, $hash)) {
            $this->logFailed($input, $user?->getId());

            throw new InvalidCredentialsException;
        }

        // Inactive account: the attempt is audited, but the HTTP response is identical
        // to the invalid-credentials one so we don't reveal that the email exists (anti-enumeration).
        if (! $user->isActive()) {
            $this->logFailed($input, $user->getId(), reason: 'inactive');

            throw new InvalidCredentialsException;
        }

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        $this->audit->log(
            action: 'auth.login',
            userId: $user->getId(),
            tenantId: $input->tenantId,
        );

        return new LoginOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastNamePaternal: $user->getLastNamePaternal(),
            lastNameMaternal: $user->getLastNameMaternal(),
            fullName: $user->getFullName(),
            isStaff: $user->isStaff(),
            token: $this->tokens->generate($user->getId()),
            roles: $roles,
            permissions: $this->extractPermissionSlugs($roles),
        );
    }

    /**
     * The attempted email is stored in struct_after for correlation (brute force).
     */
    private function logFailed(LoginInput $input, ?int $userId, ?string $reason = null): void
    {
        $struct = ['email' => $input->email];

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
