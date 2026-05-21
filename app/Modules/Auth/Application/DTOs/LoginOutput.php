<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * LoginOutput DTO
 * Datos de salida de LoginUseCase
 */
class LoginOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
        public readonly string $schoolId
    ) {
    }
}
