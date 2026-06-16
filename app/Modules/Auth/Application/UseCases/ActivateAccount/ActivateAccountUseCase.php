<?php

namespace App\Modules\Auth\Application\UseCases\ActivateAccount;

use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Domain\Contracts\ActivationRepositoryInterface;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Support\Facades\Hash;

class ActivateAccountUseCase
{
    public function __construct(
        private readonly ActivationRepositoryInterface $activations,
        private readonly TokenServiceInterface $tokens,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Activate a tenant owner or a Softlinkia staff account using a signed URL token.
     *
     * The HTTP layer is responsible for validating the signed URL signature
     * before calling this UseCase. This UseCase only enforces domain rules:
     * - The user must exist and must not already be activated.
     * - A non-staff user must belong to a tenant.
     *
     * Steps performed inside a DB transaction (delegated to the repository):
     * - Set password_hash (bcrypt, 12 rounds minimum).
     * - Set email_verified_at = now().
     * - For tenant owners, set the associated tenant's status to 'active'
     *   (skipped for staff users, who have no tenant).
     *
     * Returns the same LoginOutput shape as a standard login, EXCEPT the token is
     * null when the user's role requires 2FA: the password is set but no session
     * is issued, so the client must redirect to login and complete 2FA there.
     *
     * @throws UserNotFoundException When no pending user matches the UUID, or a
     *                               non-staff user has no tenant.
     */
    public function execute(ActivateAccountInput $input): LoginOutput
    {
        $user = $this->activations->findPendingByUuid($input->userUuid);

        if ($user === null) {
            throw new UserNotFoundException($input->userUuid);
        }

        $tenantId = $user->getTenantId();

        // Tenant owners must belong to a tenant; staff users (is_staff, no tenant)
        // activate without one.
        if ($tenantId === null && ! $user->isStaff()) {
            throw new UserNotFoundException($input->userUuid);
        }

        $passwordHash = Hash::make($input->password, ['rounds' => 12]);

        $this->activations->activate($user->getId(), $passwordHash, $tenantId);

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        // Withhold the session when 2FA is enforced for this role: the account is
        // activated (password set) but the user must sign in to enroll/verify 2FA.
        $requiresTwoFactor = $this->requiresTwoFactor($roles);

        return new LoginOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastNamePaternal: $user->getLastNamePaternal(),
            lastNameMaternal: $user->getLastNameMaternal(),
            fullName: $user->getFullName(),
            isStaff: $user->isStaff(),
            token: $requiresTwoFactor ? null : $this->tokens->generate($user->getId()),
            roles: $roles,
            permissions: $this->extractPermissionSlugs($roles),
        );
    }

    /**
     * The role mandates 2FA (the `roles.requires_2fa` flag — single source of truth).
     *
     * @param  array<Role>  $roles
     */
    private function requiresTwoFactor(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($role->requiresTwoFactor()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Role>  $roles
     * @return array<string>
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
