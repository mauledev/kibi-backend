<?php

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\Tenant as TenantModel;
use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\ActivationRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use Illuminate\Support\Facades\DB;

/**
 * Repository for account activation. Queries users without any tenant/staff scope.
 */
class EloquentActivationRepository implements ActivationRepositoryInterface
{
    /** {@inheritDoc} */
    public function findPendingByUuid(string $uuid): ?User
    {
        $model = UserModel::where('uuid', $uuid)
            ->whereNull('email_verified_at')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function activate(int $userId, string $passwordHash, ?int $tenantId): void
    {
        DB::transaction(function () use ($userId, $passwordHash, $tenantId): void {
            UserModel::where('id', $userId)->update([
                'password_hash' => $passwordHash,
                'email_verified_at' => now(),
            ]);

            // Staff users (tenantId === null) have no tenant to activate.
            if ($tenantId !== null) {
                TenantModel::where('id', $tenantId)->update([
                    'status' => 'active',
                ]);
            }
        });
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
            tenantId: $model->tenant_id,
        );
    }
}
