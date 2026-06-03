<?php

namespace App\Modules\Tenant\Domain\Entities;

use App\Modules\Auth\Domain\Entities\User;

class Tenant
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly string $name,
        private readonly string $slug,
        private readonly string $status,
        private readonly int $ownerId,
        private readonly ?User $owner = null,
        private readonly ?string $createdAt = null,
    ) {}

    /** Return the internal BIGSERIAL primary key (never exposed in responses). */
    public function getId(): int
    {
        return $this->id;
    }

    /** Return the public UUID identifier. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Return the tenant display name. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Return the URL slug used for subdomain routing. */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /** Return the current lifecycle status (pending, active, suspended, …). */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Return the internal ID of the user who owns this tenant. */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /** Return the embedded owner User entity when it was loaded. */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /** Return the creation timestamp as an ISO 8601 string, or null when not loaded. */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
