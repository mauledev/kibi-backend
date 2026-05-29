<?php

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

            [$firstName, $lastNamePaternal, $lastNameMaternal] = $this->parseName($oauthData->name);

            $newUser = new User(
                id: 0,
                uuid: '',
                email: $oauthData->email,
                firstName: $firstName,
                lastNamePaternal: $lastNamePaternal,
                lastNameMaternal: $lastNameMaternal,
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
     * Parse an OAuth display name string into first name, paternal last name, and maternal last name.
     *
     * Rules:
     *   - One word  → firstName = word, lastNamePaternal = '', lastNameMaternal = null
     *   - Two words → firstName = first word, lastNamePaternal = last word, lastNameMaternal = null
     *   - Three+ words → firstName = first word, lastNamePaternal = last word,
     *                    lastNameMaternal = everything in between (joined)
     *
     * @return array{string, string, string|null}
     */
    private function parseName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return ['', '', null];
        }

        $count = count($parts);

        if ($count === 1) {
            return [$parts[0], '', null];
        }

        $firstName = $parts[0];
        $lastNamePaternal = $parts[$count - 1];
        $lastNameMaternal = $count > 2
            ? implode(' ', array_slice($parts, 1, $count - 2))
            : null;

        return [$firstName, $lastNamePaternal, $lastNameMaternal];
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
