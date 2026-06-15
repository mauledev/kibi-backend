<?php

namespace App\Modules\Student\Domain\ValueObjects;

/**
 * Immutable payload for updating a student.
 *
 * Only non-null fields are written to the database — null means "leave unchanged."
 * Wraps the eleven update fields into a single parameter so the repository
 * contract stays within the 7-parameter limit enforced by the project quality gate.
 */
final class StudentUpdateData
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastNamePaternal = null,
        public readonly ?string $lastNameMaternal = null,
        public readonly ?string $phone = null,
        public readonly ?string $birthDate = null,
        public readonly ?string $nationalId = null,
        public readonly ?string $enrollmentNumber = null,
        public readonly ?string $gender = null,
        public readonly ?string $bloodType = null,
        public readonly ?int $groupId = null,
    ) {}
}
