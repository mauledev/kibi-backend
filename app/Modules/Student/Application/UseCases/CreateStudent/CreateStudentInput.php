<?php

namespace App\Modules\Student\Application\UseCases\CreateStudent;

/**
 * Input DTO for creating a student.
 *
 * The student is created as a pending user (no password, no email_verified_at).
 * The 'student' role is assigned automatically by the use case — the caller does
 * not provide a roleUuid. The school is provided so the role assignment is
 * scoped correctly to the enrollment school.
 */
final class CreateStudentInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $actorUuid,
        public readonly string $actorSlug,
        public readonly string $schoolUuid,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly ?string $phone,
        public readonly ?string $birthDate,
        public readonly ?string $nationalId,
        public readonly ?string $enrollmentNumber,
        public readonly ?string $gender,
        public readonly ?string $bloodType,
        public readonly ?int $groupId,
    ) {}
}
