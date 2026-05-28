<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases\OAuthLogin;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\DTOs\OAuthLoginInput;
use App\Modules\Auth\Domain\Contracts\OAuthProviderInterface;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

class OAuthLoginUseCase
{
    public function __construct(
        private readonly OAuthProviderInterface $provider,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenServiceInterface $tokens,
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Authenticate or register a user via an OAuth provider access token.
     *
     * Lookup order:
     *   1. Find by provider ID (google_id / microsoft_id).
     *   2. Fall back to email match for account linking.
     *   3. Create the user if no match is found.
     */
    public function execute(OAuthLoginInput $input): LoginOutput
    {
        $oauthData = $this->provider->getUserFromToken($input->accessToken);

        // 1. Look up by provider-specific ID
        $user = $input->provider === 'google'
            ? $this->userRepository->findByGoogleId($oauthData->providerId)
            : $this->userRepository->findByMicrosoftId($oauthData->providerId);

        // 2. Account linking — fall back to email if no provider ID match
        if ($user === null) {
            $user = $this->userRepository->findByEmail($oauthData->email);
        }

        // 3. First OAuth login — create the user with no password
        if ($user === null) {
            $googleId = $input->provider === 'google' ? $oauthData->providerId : null;
            $microsoftId = $input->provider === 'microsoft' ? $oauthData->providerId : null;

            $newUser = new User(
                id: 0,
                uuid: '',
                tenantId: $input->tenantId,
                email: $oauthData->email,
                fullName: $oauthData->name,
                passwordHash: null,
                status: 'active',
                googleId: $googleId,
                microsoftId: $microsoftId,
            );

            $user = $this->userRepository->save($newUser);
        }

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        $this->audit->log(
            action: 'auth.oauth_login',
            userId: $user->getId(),
            structAfter: ['provider' => $input->provider],
        );

        return new LoginOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            isStaff: $user->isStaff(),
            token: $this->tokens->generate($user->getId()),
            roles: $roles,
            permissions: $this->extractPermissionSlugs($roles),
        );
    }

    /** @param array<Role> $roles @return array<string> */
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
