<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entities;

use DateTime;

class User
{
    private ?DateTime $updatedAt = null;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly ?int $tenantId,
        private readonly string $email,
        private readonly string $fullName,
        private string $passwordHash,
        private string $status = 'active',
        private readonly DateTime $createdAt = new DateTime,
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isStaff(): bool
    {
        return $this->tenantId === null;
    }

    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->updatedAt = new DateTime;
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->updatedAt = new DateTime;
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
        $this->updatedAt = new DateTime;
    }
}
