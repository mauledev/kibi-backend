<?php

namespace App\Modules\Staff\Domain\Entities;

use DateTimeImmutable;

/**
 * A created Backoffice staff member. Returned by CreatePersonnelUseCase and
 * serialized by StaffMemberResource.
 *
 * `permissions` are the EFFECTIVE permission slugs granted to the member
 * (role defaults minus the ones the actor unchecked at creation time).
 */
class StaffMember
{
    /**
     * @param  array<string>  $permissions
     */
    public function __construct(
        private readonly string $uuid,
        private readonly string $role,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly WorkSchedule $workSchedule,
        private readonly array $permissions,
        private readonly bool $requires2fa,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getRole(): string
    {
        return $this->role;
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

    public function getWorkSchedule(): WorkSchedule
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
