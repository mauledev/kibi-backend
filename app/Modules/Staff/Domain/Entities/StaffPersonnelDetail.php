<?php

namespace App\Modules\Staff\Domain\Entities;

use DateTimeImmutable;

/**
 * Full read model for a single Backoffice staff member (detail view).
 *
 * `workSchedule` is nullable: staff users created before the schedule existed
 * (or accounts without one, e.g. superadmin) may not have it.
 */
class StaffPersonnelDetail
{
    /**
     * @param  array<string>  $permissions
     */
    public function __construct(
        private readonly string $uuid,
        private readonly ?string $roleSlug,
        private readonly ?string $roleName,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly ?WorkSchedule $workSchedule,
        private readonly array $permissions,
        private readonly bool $requires2fa,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getRoleSlug(): ?string
    {
        return $this->roleSlug;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getWorkSchedule(): ?WorkSchedule
    {
        return $this->workSchedule;
    }

    /** @return array<string> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function requires2fa(): bool
    {
        return $this->requires2fa;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
