<?php

namespace App\Modules\Staff\Domain\Entities;

/**
 * A Superadmin taking part in the approval ceremony (proposer or resolver).
 *
 * The internal `id` exists only for the dual-control comparison (approver must
 * differ from proposer) — it is never serialized; resources expose the uuid.
 */
class ApprovalParticipant
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly string $fullName,
        private readonly string $email,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
