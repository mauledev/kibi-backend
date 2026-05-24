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
        private readonly string $publicId,
        private readonly ?int $tenantId,
        private string $name,
        private readonly string $slug,
        private readonly int $hierarchyLevel,
        private readonly bool $isSystemRole,
        private array $permissions = [],
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable,
        private readonly ?DateTimeImmutable $deletedAt = null,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getHierarchyLevel(): int
    {
        return $this->hierarchyLevel;
    }

    public function isSystemRole(): bool
    {
        return $this->isSystemRole;
    }

    /** @return array<Permission> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

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
