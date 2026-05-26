<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases\Login;

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
    ) {}

    /**
     * @throws InvalidCredentialsException
     */
    public function execute(LoginInput $input): LoginOutput
    {
        $user = $this->userRepository->findByEmail($input->email);

        $hash = $user?->getPasswordHash();

        if (! $user || $hash === null || ! Hash::check($input->password, $hash)) {
            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            throw new InvalidCredentialsException('User is inactive');
        }

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        return new LoginOutput(
            publicId: $user->getPublicId(),
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
