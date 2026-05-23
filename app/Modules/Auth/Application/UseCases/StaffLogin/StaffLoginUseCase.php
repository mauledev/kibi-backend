<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases\StaffLogin;

use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class StaffLoginUseCase
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

        if (! $user || ! Hash::check($input->password, $user->getPasswordHash())) {
            throw new InvalidCredentialsException;
        }

        if (! $user->isActive()) {
            throw new InvalidCredentialsException('User is inactive');
        }

        if (! $user->isStaff()) {
            throw new InvalidCredentialsException;
        }

        return new LoginOutput(
            publicId: $user->getPublicId(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            isStaff: true,
            token: $this->tokens->generate($user->getId()),
            roles: $this->roles->findActiveRolesForUser($user->getId()),
        );
    }
}
