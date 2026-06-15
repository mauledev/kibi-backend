<?php

namespace App\Modules\Student\Domain\Entities;

use DateTime;

/**
 * Domain Entity for the Student module.
 *
 * Carries both the user identity fields and the student-specific profile fields.
 * No framework dependencies. Business logic (getFullName) lives here so it can
 * be called from any layer without coupling to Eloquent or resources.
 */
class Student
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
        private readonly ?string $birthDate,
        private readonly ?string $nationalId,
        private readonly ?string $enrollmentNumber,
        private readonly ?string $gender,
        private readonly ?string $bloodType,
        private readonly ?string $groupUuid,
        private readonly ?string $groupName,
        private readonly DateTime $createdAt,
    ) {}

    /** Return the internal surrogate key — used only within Infrastructure for FK lookups. */
    public function getId(): int
    {
        return $this->id;
    }

    /** Return the public UUID of the student profile record. */
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

    /** Return the student's first (given) name. */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /** Return the student's paternal surname. */
    public function getLastNamePaternal(): string
    {
        return $this->lastNamePaternal;
    }

    /** Return the student's maternal surname, or null when not provided. */
    public function getLastNameMaternal(): ?string
    {
        return $this->lastNameMaternal;
    }

    /** Return the student's phone number, or null when not provided. */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /** Return the lifecycle status of the student's user account. */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Return the student's birth date in Y-m-d format, or null when not provided. */
    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    /** Return the national identification number (CURP/RUT/CPF/DNI), or null when not provided. */
    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    /** Return the enrollment number assigned by the school, or null when not provided. */
    public function getEnrollmentNumber(): ?string
    {
        return $this->enrollmentNumber;
    }

    /** Return the student's gender (male, female, other, prefer_not_to_say), or null. */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /** Return the student's blood type, or null when not provided. */
    public function getBloodType(): ?string
    {
        return $this->bloodType;
    }

    /** Return the UUID of the group the student belongs to, or null when unassigned. */
    public function getGroupUuid(): ?string
    {
        return $this->groupUuid;
    }

    /** Return the name of the group the student belongs to, or null when unassigned. */
    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    /** Return the timestamp when this student profile was created. */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Concatenate first name and paternal last name, appending maternal last name when present.
     *
     * Example: "Ana García López" or "Ana García".
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
