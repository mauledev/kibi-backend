<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /** {@inheritDoc} */
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->where('email', $email)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?User
    {
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function save(User $user): User
    {
        $model = UserModel::create([
            'tenant_id' => $user->getTenantId(),
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
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->findOrFail($user->getId());

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
        return UserModel::where('tenant_id', $this->context->tenantId)
            ->where('id', $id)
            ->delete() > 0;
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            publicId: $model->public_id,
            tenantId: $model->tenant_id,
            email: $model->email,
            fullName: $model->full_name,
            passwordHash: $model->password_hash,
            status: $model->status,
        );
    }
}
