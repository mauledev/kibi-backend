<?php

namespace App\Modules\Roles\Infrastructure\Repositories;

use App\Models\Role as RoleModel;
use App\Models\UserRoleAssignment as AssignmentModel;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

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
    public function findActiveByUserIdAndSchool(int $userId, int $schoolId): array
    {
        $models = AssignmentModel::where('user_id', $userId)
            ->where('school_id', $schoolId)
            ->whereNull('revoked_at')
            ->get();

        return $models->map(fn (AssignmentModel $m) => $this->toDomain($m))->all();
    }

    /** {@inheritDoc} */
    public function findActiveRoleSlugsForUserInSchool(int $userId, int $schoolId): array
    {
        return AssignmentModel::join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $userId)
            ->where('user_role_assignments.school_id', $schoolId)
            ->whereNull('user_role_assignments.revoked_at')
            ->pluck('roles.slug')
            ->all();
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

    /** {@inheritDoc} */
    public function createOwnerAssignment(int $userId): UserRoleAssignment
    {
        $ownerRole = RoleModel::firstOrCreate(
            ['slug' => 'owner', 'tenant_id' => null],
            ['name' => 'Owner', 'hierarchy_level' => 2, 'is_system_role' => false],
        );

        $model = AssignmentModel::create([
            'user_id' => $userId,
            'role_id' => $ownerRole->id,
            'school_id' => null,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        return $this->toDomain($model);
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?UserRoleAssignment
    {
        $model = AssignmentModel::where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findById(int $id): ?UserRoleAssignment
    {
        $model = AssignmentModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findRoleSlugByAssignmentId(int $assignmentId): ?string
    {
        return AssignmentModel::join('roles', 'roles.id', '=', 'user_role_assignments.role_id')
            ->where('user_role_assignments.id', $assignmentId)
            ->value('roles.slug');
    }

    /** {@inheritDoc} */
    public function addDenial(int $assignmentId, int $permissionId): bool
    {
        $inserted = DB::table('user_role_assignment_denials')->insertOrIgnore([
            'role_user_assignment_id' => $assignmentId,
            'permission_id' => $permissionId,
        ]);

        return $inserted > 0;
    }

    /** {@inheritDoc} */
    public function removeDenial(int $assignmentId, int $permissionId): void
    {
        DB::table('user_role_assignment_denials')
            ->where('role_user_assignment_id', $assignmentId)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    private function toDomain(AssignmentModel $model): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $model->id,
            uuid: $model->uuid,
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
