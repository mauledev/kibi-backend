<?php

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
    public function findByUuid(string $uuid): ?User
    {
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->where('uuid', $uuid)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

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
    public function findByGoogleId(string $googleId): ?User
    {
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->where('google_id', $googleId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findByMicrosoftId(string $microsoftId): ?User
    {
        $model = UserModel::where('tenant_id', $this->context->tenantId)
            ->where('microsoft_id', $microsoftId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function save(User $user): User
    {
        $model = UserModel::create([
            'tenant_id' => $this->context->tenantId,
            'email' => $user->getEmail(),
            'first_name' => $user->getFirstName(),
            'last_name_paternal' => $user->getLastNamePaternal(),
            'last_name_maternal' => $user->getLastNameMaternal(),
            'password_hash' => $user->getPasswordHash(),
            'google_id' => $user->getGoogleId(),
            'microsoft_id' => $user->getMicrosoftId(),
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
            'first_name' => $user->getFirstName(),
            'last_name_paternal' => $user->getLastNamePaternal(),
            'last_name_maternal' => $user->getLastNameMaternal(),
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
            uuid: $model->uuid,
            email: $model->email,
            firstName: $model->first_name,
            lastNamePaternal: $model->last_name_paternal,
            lastNameMaternal: $model->last_name_maternal,
            passwordHash: $model->password_hash,
            status: $model->status,
            googleId: $model->google_id,
            microsoftId: $model->microsoft_id,
            isStaff: (bool) $model->is_staff,
            tenantId: $model->tenant_id,
        );
    }
}
