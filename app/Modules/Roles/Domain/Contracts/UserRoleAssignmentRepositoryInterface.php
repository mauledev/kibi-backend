<?php

declare(strict_types=1);

namespace App\Modules\Roles\Domain\Contracts;

use App\Modules\Roles\Domain\Entities\UserRoleAssignment;

interface UserRoleAssignmentRepositoryInterface
{
    /**
     * Return all active assignments for a user (revoked_at IS NULL).
     *
     * @return array<UserRoleAssignment>
     */
    public function findActiveByUserId(int $userId): array;

    /**
     * Find the active assignment for a user + role combination.
     */
    public function findActiveByUserAndRole(int $userId, int $roleId, ?int $schoolId): ?UserRoleAssignment;

    /**
     * Persist a new role assignment and return the domain entity.
     */
    public function create(
        int $userId,
        int $roleId,
        ?int $schoolId,
        ?int $assignedBy,
    ): UserRoleAssignment;

    /**
     * Revoke an assignment by setting revoked_at to now().
     */
    public function revoke(int $assignmentId): UserRoleAssignment;
}
