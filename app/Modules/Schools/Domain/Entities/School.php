<?php

namespace App\Modules\Schools\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * School domain entity.
 *
 * Represents a school within a tenant (company). Pure PHP, no framework
 * dependencies. Eloquent models in Infrastructure map to/from this entity.
 */
final class School
{
    /**
     * @param  array<string, mixed>|null  $address
     */
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $tenantId,
        private string $name,
        private string $slug,
        private ?array $address,
        private ?string $phone,
        private string $status,
        private readonly ?DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt,
    ) {}

    /** Returns the internal surrogate key (Infrastructure use only). */
    public function getId(): int
    {
        return $this->id;
    }

    /** Returns the public UUID used in routes and responses. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Returns the tenant this school belongs to. */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /** Returns the school display name. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Returns the URL-safe slug. */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Returns the structured address or null when not set.
     *
     * @return array<string, mixed>|null
     */
    public function getAddress(): ?array
    {
        return $this->address;
    }

    /** Returns the contact phone number or null. */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /** Returns the current lifecycle status (active or suspended). */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Returns the creation timestamp or null. */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Returns the last-update timestamp or null. */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Returns the soft-delete timestamp or null when not deleted. */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    // Behavior methods

    /** Returns true when the school is in the active lifecycle state. */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Returns true when the school is suspended. */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** Returns true when the school has been soft-deleted (deactivated). */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** Renames the school; throws on empty value. */
    public function rename(string $newName): void
    {
        if (trim($newName) === '') {
            throw new InvalidArgumentException('School name cannot be empty');
        }

        $this->name = $newName;
    }

    /** Changes the URL slug; throws on empty value. */
    public function changeSlug(string $newSlug): void
    {
        if (trim($newSlug) === '') {
            throw new InvalidArgumentException('School slug cannot be empty');
        }

        $this->slug = $newSlug;
    }

    /**
     * Replaces the address.
     *
     * @param  array<string, mixed>|null  $address
     */
    public function updateAddress(?array $address): void
    {
        $this->address = $address;
    }

    /** Replaces the contact phone number. */
    public function updatePhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    /** Transitions the school to suspended; idempotent. */
    public function suspend(): void
    {
        if ($this->isSuspended()) {
            return;
        }

        $this->status = 'suspended';
    }

    /** Transitions the school to active; idempotent. */
    public function activate(): void
    {
        if ($this->isActive()) {
            return;
        }

        $this->status = 'active';
    }
}
