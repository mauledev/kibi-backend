<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\Email;
use App\Models\User as UserModel;

/**
 * EloquentUserRepository
 * Implementación concreta usando Eloquent
 * Esta es la ÚNICA que conoce de Eloquent/BD
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function save(User $user): User
    {
        $model = UserModel::create([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'password' => $user->getPasswordHash(),
            'role' => $user->getRole(),
            'school_id' => $user->getSchoolId(),
            'status' => $user->getStatus(),
        ]);

        return $this->toDomain($model);
    }

    public function findById(string $id): ?User
    {
        $model = UserModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::where('email', $email->getValue())->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function update(User $user): User
    {
        $model = UserModel::findOrFail($user->getId());
        $model->update([
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'password' => $user->getPasswordHash(),
            'role' => $user->getRole(),
            'status' => $user->getStatus(),
        ]);

        return $this->toDomain($model);
    }

    public function delete(string $id): bool
    {
        return UserModel::destroy($id) > 0;
    }

    public function findBySchool(string $schoolId): array
    {
        $models = UserModel::where('school_id', $schoolId)->get();
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    /**
     * Mapea modelo Eloquent a Entity de dominio
     */
    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            email: $model->email,
            name: $model->name,
            passwordHash: $model->password,
            role: $model->role,
            schoolId: $model->school_id,
            status: $model->status
        );
    }
}
