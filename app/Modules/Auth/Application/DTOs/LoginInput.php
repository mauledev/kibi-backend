<?php

namespace App\Modules\Auth\Application\DTOs;

/**
 * LoginInput DTO
 * Datos de entrada para LoginUseCase
 */
class LoginInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {}
}
