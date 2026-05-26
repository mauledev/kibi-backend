<?php

namespace App\Modules\Roles\Domain\Entities;

use DateTimeImmutable;

class UserRoleAssignment
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly int $roleId,
        private readonly ?int $schoolId,
        private readonly ?int $assignedBy,
        private readonly DateTimeImmutable $assignedAt,
        private ?DateTimeImmutable $revokedAt = null,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRoleId(): int
    {
        return $this->roleId;
    }

    public function getSchoolId(): ?int
    {
        return $this->schoolId;
    }

    public function getAssignedBy(): ?int
    {
        return $this->assignedBy;
    }

    public function getAssignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable;
    }
}
