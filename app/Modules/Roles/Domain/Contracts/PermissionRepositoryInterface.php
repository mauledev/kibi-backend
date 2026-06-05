<?php

namespace App\Modules\Roles\Domain\Contracts;

use App\Modules\Roles\Domain\Entities\Permission;

interface PermissionRepositoryInterface
{
    /**
     * Return all system permissions (school_id IS NULL on their category).
     *
     * @return array<Permission>
     */
    public function findAll(): array;

    /**
     * Find a permission by its public UUID.
     */
    public function findByUuid(string $uuid): ?Permission;

    /**
     * Find a permission by its internal ID.
     */
    public function findById(int $id): ?Permission;

    /**
     * Find a permission by its slug.
     */
    public function findBySlug(string $slug): ?Permission;

    /**
     * Return all permissions merged from the given role IDs.
     *
     * @param  array<int>  $roleIds
     * @return array<Permission>
     */
    public function findByRoleIds(array $roleIds): array;

    /**
     * Return the category scope ('staff'|'tenant'|'school') for the given category id,
     * or null when the category does not exist.
     */
    public function findCategoryScope(int $categoryId): ?string;

    /**
     * Return all permissions that belong to the given category.
     *
     * @return array<Permission>
     */
    public function findByCategoryId(int $categoryId): array;
}
