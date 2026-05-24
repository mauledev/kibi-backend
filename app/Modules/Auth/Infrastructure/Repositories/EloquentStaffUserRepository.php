<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;

class EloquentStaffUserRepository implements UserRepositoryInterface
{
    /** {@inheritDoc} */
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::whereNull('tenant_id')
            ->where('email', $email)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?User
    {
        $model = UserModel::whereNull('tenant_id')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function save(User $user): User
    {
        $model = UserModel::create([
            'tenant_id' => null,
            'email' => $user->getEmail(),
            'full_name' => $user->getFullName(),
            'password_hash' => $user->getPasswordHash(),
            'status' => $user->getStatus(),
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function update(User $user): User
    {
        $model = UserModel::whereNull('tenant_id')->findOrFail($user->getId());

        $model->update([
            'full_name' => $user->getFullName(),
            'password_hash' => $user->getPasswordHash(),
            'status' => $user->getStatus(),
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function delete(int $id): bool
    {
        return UserModel::whereNull('tenant_id')
            ->where('id', $id)
            ->delete() > 0;
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            publicId: $model->public_id,
            tenantId: null,
            email: $model->email,
            fullName: $model->full_name,
            passwordHash: $model->password_hash,
            status: $model->status,
        );
    }
}
