<?php

namespace App\Modules\Roles\Domain\Entities;

use DateTimeImmutable;

class Role
{
    /**
     * @param  array<Permission>  $permissions
     */
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly ?int $tenantId,
        private readonly ?int $categoryId,
        private string $name,
        private readonly string $slug,
        private readonly int $hierarchyLevel,
        private readonly bool $isSystemRole,
        private array $permissions = [],
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable,
        private readonly ?DateTimeImmutable $deletedAt = null,
    ) {}

    /** Return the internal primary key. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Return the public UUID. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Return the tenant id this role belongs to, or null for global/system roles. */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /** Return the permission category id that bounds this role, or null for uncategorised roles. */
    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    /**
     * Returns true when this is a custom role:
     * belongs to a tenant, has no category, and is not a reserved admin role.
     */
    public function isCustomRole(): bool
    {
        return $this->tenantId !== null
            && $this->categoryId === null
            && ! in_array($this->slug, ['owner', 'school_manager'], true);
    }

    /** Return the human-readable role name. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Return the role slug used for programmatic identification (e.g. 'school_manager'). */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /** Return the hierarchy level (lower = higher authority). Kept for future use. */
    public function getHierarchyLevel(): int
    {
        return $this->hierarchyLevel;
    }

    /** Return true when this role is a Softlinkia staff role (tenant_id IS NULL, is_system_role = true). */
    public function isSystemRole(): bool
    {
        return $this->isSystemRole;
    }

    /** @return array<Permission> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /** Return the timestamp when this role was created. */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Return the soft-delete timestamp, or null when the role is active. */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** Return true when this role has been soft-deleted. */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Returns true if this role has the given permission slug.
     */
    public function hasPermission(string $slug): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getSlug() === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the permission list (used by Infrastructure after eager-loading).
     *
     * @param  array<Permission>  $permissions
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }
}
