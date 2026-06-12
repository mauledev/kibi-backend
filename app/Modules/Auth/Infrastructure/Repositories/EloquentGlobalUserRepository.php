<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;

/**
 * Repository for user operations that bypass tenant/staff scoping.
 * Used exclusively in staff-level UseCases (e.g. tenant creation).
 */
class EloquentGlobalUserRepository implements GlobalUserRepositoryInterface
{
    /** {@inheritDoc} */
    public function existsByEmail(string $email): bool
    {
        return UserModel::where('email', $email)->exists();
    }

    /** {@inheritDoc} */
    public function createPending(
        string $email,
        string $firstName,
        string $lastNamePaternal,
        ?string $lastNameMaternal,
    ): User {
        $model = UserModel::create([
            'is_staff' => false,
            'tenant_id' => null,
            'email' => $email,
            'first_name' => $firstName,
            'last_name_paternal' => $lastNamePaternal,
            'last_name_maternal' => $lastNameMaternal,
            'password_hash' => null,
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function setTenantId(int $userId, int $tenantId): void
    {
        UserModel::where('id', $userId)->update(['tenant_id' => $tenantId]);
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?User
    {
        $model = UserModel::where('uuid', $uuid)->first();

        return $model !== null ? $this->toDomain($model) : null;
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
            isStaff: (bool) $model->is_staff,
            emailVerifiedAt: $model->email_verified_at?->toDateTime(),
        );
    }
}
