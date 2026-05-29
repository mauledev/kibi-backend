<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases\GetMe;

use App\Modules\Auth\Application\DTOs\MeOutput;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\UserNotFoundException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;

class GetStaffMeUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * @throws UserNotFoundException
     */
    public function execute(int $userId): MeOutput
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new UserNotFoundException;
        }

        $roles = $this->roles->findActiveRolesForUser($userId);

        return new MeOutput(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastNamePaternal: $user->getLastNamePaternal(),
            lastNameMaternal: $user->getLastNameMaternal(),
            fullName: $user->getFullName(),
            isStaff: $user->isStaff(),
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
