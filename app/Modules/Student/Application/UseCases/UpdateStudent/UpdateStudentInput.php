<?php

namespace App\Modules\Student\Application\UseCases\UpdateStudent;

/**
 * Input DTO for updating a student.
 *
 * All profile fields are nullable — only provided fields are updated.
 * The actorId (internal user id) is needed for the audit log entry.
 * The student is identified by the associated user's public UUID.
 */
final class UpdateStudentInput
{
    public function __construct(
        public readonly string $userUuid,
        public readonly ?string $firstName,
        public readonly ?string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly ?string $phone,
        public readonly ?string $birthDate,
        public readonly ?string $nationalId,
        public readonly ?string $enrollmentNumber,
        public readonly ?string $gender,
        public readonly ?string $bloodType,
        public readonly ?int $groupId,
        public readonly int $actorId,
    ) {}
}
