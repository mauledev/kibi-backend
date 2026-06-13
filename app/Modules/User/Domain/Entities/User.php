<?php

namespace App\Modules\User\Domain\Entities;

use DateTime;

/**
 * Read-oriented Domain Entity for the User module.
 *
 * This entity is used exclusively for listing and retrieval — it carries the
 * user's identity fields plus their active role assignments, each represented
 * as a compact RoleAssignment value object. It is deliberately separate from
 * the Auth module's User entity, which focuses on authentication concerns.
 *
 * No framework dependencies. Business logic (getFullName) lives here so it
 * can be called from any layer without coupling to Eloquent or resources.
 */
class User
{
    /**
     * @param  array<int, RoleAssignment>  $roles  Active role assignments for this user.
     */
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastNamePaternal,
        private readonly ?string $lastNameMaternal,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly DateTime $createdAt,
        private readonly ?DateTime $emailVerifiedAt,
        private readonly array $roles = [],
    ) {
    }

    /** Return the internal surrogate key — used only within Infrastructure for FK lookups. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Return the public UUID exposed in routes and API responses. */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Return the user's email address. */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** Return the user's first (given) name. */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /** Return the user's paternal surname. */
    public function getLastNamePaternal(): string
    {
        return $this->lastNamePaternal;
    }

    /** Return the user's maternal surname, or null when not provided. */
    public function getLastNameMaternal(): ?string
    {
        return $this->lastNameMaternal;
    }

    /** Return the user's phone number, or null when not provided. */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /** Return the lifecycle status of the user (e.g. active, inactive, suspended). */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Return the timestamp when this user record was created. */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /** Summary of getEmailVerifiedAt */
    public function getEmailVerifiedAt(): ?DateTime
    {
        return $this->emailVerifiedAt;
    }

    /**
     * Return the active role assignments for this user.
     *
     * @return array<int, RoleAssignment>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Concatenate first name and paternal last name, appending maternal last name when present.
     *
     * Example: "Mauricio Ledesma García" or "Mauricio Ledesma".
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
