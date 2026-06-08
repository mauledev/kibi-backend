<?php

namespace App\Modules\Roles\Domain\Entities;

use DateTimeImmutable;

class Permission
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $categoryId,
        private readonly string $name,
        private readonly string $slug,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable,
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

    /** Return the category this permission belongs to. */
    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    /** Return the human-readable permission name. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Return the permission slug used in authorization checks (e.g. 'grade.publish'). */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /** Return the timestamp when this permission was created. */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
