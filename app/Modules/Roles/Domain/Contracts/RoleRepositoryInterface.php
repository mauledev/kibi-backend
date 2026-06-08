<?php

namespace App\Modules\Roles\Domain\Contracts;

use App\Modules\Roles\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    /**
     * Return all active (non-deleted) roles for the current tenant,
     * plus global system roles (tenant_id IS NULL).
     *
     * @return array<Role>
     */
    public function findAll(): array;

    /**
     * Find a single role by its public UUID within the current tenant scope.
     */
    public function findByUuid(string $uuid): ?Role;

    /**
     * Find a role by its internal ID within the current tenant scope.
     */
    public function findById(int $id): ?Role;

    /**
     * Find a role by slug within the current tenant scope.
     */
    public function findBySlug(string $slug): ?Role;

    /**
     * Persist a new role and return the domain entity.
     */
    public function create(
        ?int $tenantId,
        ?int $categoryId,
        string $name,
        string $slug,
        int $hierarchyLevel,
        bool $isSystemRole,
    ): Role;

    /**
     * Count existing custom roles for a tenant.
     * Custom roles: tenant_id = X, category_id IS NULL, slug NOT IN ('owner', 'gestor_escuelas').
     */
    public function countCustomRoles(int $tenantId): int;

    /**
     * Associate a role with the given school IDs (custom_role_schools).
     *
     * @param  array<int>  $schoolIds
     */
    public function attachSchools(int $roleId, array $schoolIds): void;

    /**
     * Return the tenant's custom_roles_limit, or null if not configured.
     */
    public function getCustomRolesLimit(int $tenantId): ?int;

    /**
     * Update the tenant's custom_roles_limit.
     */
    public function setCustomRolesLimit(int $tenantId, int $limit): void;

    /**
     * Update mutable fields of an existing role.
     */
    public function update(Role $role): Role;

    /**
     * Soft-delete a role by its public UUID.
     */
    public function delete(string $uuid): bool;

    /**
     * Return all active roles for a user by their internal user ID.
     *
     * @return array<Role>
     */
    public function findActiveRolesForUser(int $userId): array;

    /**
     * Attach a permission to a role (role_permissions pivot).
     */
    public function attachPermission(int $roleId, int $permissionId): void;

    /**
     * Detach a permission from a role (role_permissions pivot).
     */
    public function detachPermission(int $roleId, int $permissionId): void;
}
