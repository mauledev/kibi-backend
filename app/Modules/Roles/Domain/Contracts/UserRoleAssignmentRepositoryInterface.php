<?php

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
     * Return all active assignments for a user in a specific school (revoked_at IS NULL).
     *
     * @return array<UserRoleAssignment>
     */
    public function findActiveByUserIdAndSchool(int $userId, int $schoolId): array;

    /**
     * Return role slugs of all active assignments for a user in a specific school.
     *
     * @return array<string>
     */
    public function findActiveRoleSlugsForUserInSchool(int $userId, int $schoolId): array;

    /**
     * Find a user_role_assignment by its public UUID.
     * Returns the domain entity or null when not found.
     */
    public function findByUuid(string $uuid): ?UserRoleAssignment;

    /**
     * Find a user_role_assignment by its internal ID.
     * Returns the domain entity or null when not found.
     */
    public function findById(int $id): ?UserRoleAssignment;

    /**
     * Return the role slug for the given assignment id, or null.
     */
    public function findRoleSlugByAssignmentId(int $assignmentId): ?string;

    /**
     * Add a permission denial to an assignment.
     * Idempotent — does nothing if the denial already exists.
     * Returns true when a new denial was created, false when it already existed.
     */
    public function addDenial(int $assignmentId, int $permissionId): bool;

    /**
     * Remove a permission denial from an assignment.
     * Idempotent — does nothing if the denial does not exist.
     */
    public function removeDenial(int $assignmentId, int $permissionId): void;

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

    /**
     * First-or-create the system 'owner' role (tenant_id = null, hierarchy_level = 2)
     * and create an assignment for the given user at tenant level (school_id = null).
     *
     * This is the only place where the owner role may be assigned.
     * The role slug 'owner' is reserved — use AssignRoleToUserUseCase for any other role.
     */
    public function createOwnerAssignment(int $userId): UserRoleAssignment;
}
