<?php

namespace App\Modules\Auth\Application\UseCases\StaffLogin;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

/**
 * Builds an authenticated staff session (token + profile + roles/permissions)
 * for a user id. Single place that mints a staff LoginOutput — used by the
 * normal login (no 2FA) and by the 2FA completion endpoints.
 */
class IssueStaffSessionUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roles,
        private readonly TokenServiceInterface $tokens,
        private readonly AuditLoggerInterface $audit,
        private readonly PolicyAcceptanceChecker $policy,
    ) {}

    /**
     * @throws InvalidCredentialsException When the user no longer exists.
     */
    public function execute(int $userId): LoginOutput
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        $roles = $this->roles->findActiveRolesForUser($userId);

        $this->audit->log(action: 'auth.login', userId: $userId);

        return new LoginOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastNamePaternal: $user->getLastNamePaternal(),
            lastNameMaternal: $user->getLastNameMaternal(),
            fullName: $user->getFullName(),
            isStaff: true,
            token: $this->tokens->generate($userId),
            roles: $roles,
            permissions: $this->extractPermissionSlugs($roles),
            mustAcceptPolicy: $this->policy->mustAccept($userId, $this->roleSlugs($roles)),
        );
    }

    /**
     * @param  array<Role>  $roles
     * @return array<string>
     */
    private function roleSlugs(array $roles): array
    {
        return array_map(static fn (Role $role): string => $role->getSlug(), $roles);
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
