<?php

namespace App\Modules\Roles\Domain\Entities;

use DateTimeImmutable;

class Permission
{
    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $categoryId,
        private readonly string $name,
        private readonly string $slug,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
