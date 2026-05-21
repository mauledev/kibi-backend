<?php

namespace App\Modules\Auth\Domain\Repositories;

use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\ValueObjects\Email;

/**
 * UserRepositoryInterface
 * Contrato que debe cumplir cualquier implementación de persistencia
 */
interface UserRepositoryInterface
{
    /**
     * Guardar usuario nuevo
     */
    public function save(User $user): User;

    /**
     * Buscar por ID
     */
    public function findById(string $id): ?User;

    /**
     * Buscar por email
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Actualizar usuario
     */
    public function update(User $user): User;

    /**
     * Eliminar usuario
     */
    public function delete(string $id): bool;

    /**
     * Listar usuarios por escuela
     */
    public function findBySchool(string $schoolId): array;
}
