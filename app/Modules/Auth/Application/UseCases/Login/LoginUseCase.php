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

        // Cuenta inactiva: se audita el intento, pero la respuesta HTTP es idéntica
        // a la de credenciales inválidas para no revelar que el email existe (anti-enumeration).
        if (! $user->isActive()) {
            $this->logFailed($input, $user->getId(), reason: 'inactive');

            throw new InvalidCredentialsException;
        }

        $roles = $this->roles->findActiveRolesForUser($user->getId());

        $this->audit->log(
            action: 'auth.login.success',
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
     * el email se guarda en struct_after para correlación (brute force).
     */
    private function logFailed(LoginInput $input, ?int $userId, ?string $reason = null): void
    {
        $struct = ['email' => $input->email];

        if ($reason !== null) {
            $struct['reason'] = $reason;
        }

        $this->audit->log(
            action: 'auth.login.failed',
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
