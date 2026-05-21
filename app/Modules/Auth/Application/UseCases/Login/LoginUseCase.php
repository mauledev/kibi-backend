<?php

namespace App\Modules\Auth\Application\UseCases\Login;

use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\Email;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Application\DTOs\LoginInput;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use Illuminate\Support\Facades\Hash;

/**
 * LoginUseCase
 * Orquesta el flujo de login
 * No contiene lógica HTTP, es reutilizable desde API, CLI, Jobs, etc
 */
class LoginUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(LoginInput $input): LoginOutput
    {
        // 1. Crear valor objeto Email (valida automáticamente)
        $email = Email::create($input->email);

        // 2. Buscar usuario
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new InvalidCredentialsException();
        }

        // 3. Verificar contraseña
        if (!Hash::check($input->password, $user->getPasswordHash())) {
            throw new InvalidCredentialsException();
        }

        // 4. Verificar que esté activo
        if (!$user->isActive()) {
            throw new InvalidCredentialsException('Usuario desactivado');
        }

        // 5. Retornar output
        return new LoginOutput(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
            role: $user->getRole(),
            schoolId: $user->getSchoolId()
        );
    }
}
