<?php

namespace App\Modules\Auth\Domain\Entities;

use DateTime;

class User
{
    private ?DateTime $updatedAt = null;

    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private ?string $passwordHash,
        private string $status = 'active',
        private readonly DateTime $createdAt = new DateTime,
        private readonly ?string $googleId = null,
        private readonly ?string $microsoftId = null,
        private readonly bool $isStaff = false,
        private readonly ?int $tenantId = null,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastNamePaternal(): string
    {
        return $this->lastNamePaternal;
    }

    public function getLastNameMaternal(): ?string
    {
        return $this->lastNameMaternal;
    }

    /**
     * Concatenates first name and paternal last name, appending maternal last name when present.
     */
    public function getFullName(): string
    {
        $name = "{$this->firstName} {$this->lastNamePaternal}";

        if ($this->lastNameMaternal !== null) {
            $name .= " {$this->lastNameMaternal}";
        }

        return $name;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function getMicrosoftId(): ?string
    {
        return $this->microsoftId;
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
        return $this->isStaff;
    }

    /** Return the tenant ID this user belongs to, or null for staff users. */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
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
