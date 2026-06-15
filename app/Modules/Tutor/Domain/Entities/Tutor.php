<?php

namespace App\Modules\Tutor\Domain\Entities;

use DateTime;

/**
 * Domain Entity for the Tutor module.
 *
 * Carries both the user identity fields and the tutor-specific profile fields.
 * No framework dependencies. Business logic (getFullName) lives here so it can
 * be called from any layer without coupling to Eloquent or resources.
 */
class Tutor
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly int $userId,
        private readonly string $userUuid,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly ?string $occupation,
        private readonly DateTime $createdAt,
    ) {}

    /** Return the internal surrogate key — used only within Infrastructure for FK lookups. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Return the public UUID of the tutor profile record. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Return the internal user surrogate key. */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /** Return the public UUID of the associated user (used in routes and API responses). */
    public function getUserUuid(): string
    {
        return $this->userUuid;
    }

    /** Return the user's email address. */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** Return the tutor's first (given) name. */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /** Return the tutor's paternal surname. */
    public function getLastNamePaternal(): string
    {
        return $this->lastNamePaternal;
    }

    /** Return the tutor's maternal surname, or null when not provided. */
    public function getLastNameMaternal(): ?string
    {
        return $this->lastNameMaternal;
    }

    /** Return the tutor's phone number, or null when not provided. */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /** Return the lifecycle status of the tutor's user account. */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Return the tutor's occupation, or null when not provided. */
    public function getOccupation(): ?string
    {
        return $this->occupation;
    }

    /** Return the timestamp when this tutor profile was created. */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Concatenate first name and paternal last name, appending maternal last name when present.
     *
     * Example: "Juan García López" or "Juan García".
     */
    public function getFullName(): string
    {
        $name = "{$this->firstName} {$this->lastNamePaternal}";

        if ($this->lastNameMaternal !== null) {
            $name .= " {$this->lastNameMaternal}";
        }

        return $name;
    }
}
