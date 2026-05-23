<?php

declare(strict_types=1);

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\UserRoleAssignment as AssignmentModel;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use DateTimeImmutable;

class EloquentUserRoleAssignmentRepository implements UserRoleAssignmentRepositoryInterface
{
    /** {@inheritDoc} */
    public function findActiveByUserId(int $userId): array
    {
        $models = AssignmentModel::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->get();

        return $models->map(fn (AssignmentModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findActiveByUserAndRole(int $userId, int $roleId, ?int $schoolId): ?UserRoleAssignment
    {
        $query = AssignmentModel::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->whereNull('revoked_at');

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        } else {
            $query->whereNull('school_id');
        }

        $model = $query->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function create(
        int $userId,
        int $roleId,
        ?int $schoolId,
        ?int $assignedBy,
    ): UserRoleAssignment {
        $model = AssignmentModel::create([
            'user_id' => $userId,
            'role_id' => $roleId,
            'school_id' => $schoolId,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function revoke(int $assignmentId): UserRoleAssignment
    {
        $model = AssignmentModel::findOrFail($assignmentId);
        $model->update(['revoked_at' => now()]);

        return $this->toDomain($model);
    }

    private function toDomain(AssignmentModel $model): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $model->id,
            userId: $model->user_id,
            roleId: $model->role_id,
            schoolId: $model->school_id,
            assignedBy: $model->assigned_by,
            assignedAt: new DateTimeImmutable($model->assigned_at->toIso8601String()),
            revokedAt: $model->revoked_at !== null
                ? new DateTimeImmutable($model->revoked_at->toIso8601String())
                : null,
        );
    }
}
