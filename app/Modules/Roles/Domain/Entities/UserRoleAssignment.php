<?php

namespace App\Modules\Roles\Domain\Entities;

use DateTimeImmutable;

class UserRoleAssignment
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $userId,
        private readonly int $roleId,
        private readonly ?int $schoolId,
        private readonly ?int $assignedBy,
        private readonly DateTimeImmutable $assignedAt,
        private ?DateTimeImmutable $revokedAt = null,
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

    /** Return the internal user id that holds this assignment. */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /** Return the internal role id for this assignment. */
    public function getRoleId(): int
    {
        return $this->roleId;
    }

    /** Return the school id this assignment is scoped to, or null for tenant-level assignments. */
    public function getSchoolId(): ?int
    {
        return $this->schoolId;
    }

    /** Return the internal user id of the actor who created this assignment, or null when system-created. */
    public function getAssignedBy(): ?int
    {
        return $this->assignedBy;
    }

    /** Return the timestamp when this assignment was created. */
    public function getAssignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    /** Return the timestamp when this assignment was revoked, or null if still active. */
    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** Return true when this assignment has not been revoked. */
    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }

    /** Mark this assignment as revoked at the current time. */
    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable;
    }
}
