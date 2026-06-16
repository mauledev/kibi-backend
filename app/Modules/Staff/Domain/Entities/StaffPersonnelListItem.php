<?php

namespace App\Modules\Staff\Domain\Entities;

use DateTimeImmutable;

/**
 * Compact read model for the Backoffice staff list table.
 */
class StaffPersonnelListItem
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private readonly string $email,
        private readonly ?string $roleSlug,
        private readonly ?string $roleName,
        private readonly string $status,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function getUuid(): string
    {
        return $this->uuid;
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

    public function getFullName(): string
    {
        $name = "{$this->firstName} {$this->lastNamePaternal}";

        if ($this->lastNameMaternal !== null && $this->lastNameMaternal !== '') {
            $name .= " {$this->lastNameMaternal}";
        }

        return $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoleSlug(): ?string
    {
        return $this->roleSlug;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
