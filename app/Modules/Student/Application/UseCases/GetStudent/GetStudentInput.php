<?php

namespace App\Modules\Student\Application\UseCases\GetStudent;

/**
 * Input DTO for retrieving a single student by their user UUID.
 *
 * The student is identified by the user's public UUID (not the student_profiles uuid)
 * because all public routes use the user UUID as the canonical identifier.
 */
final class GetStudentInput
{
    public function __construct(
        public readonly string $userUuid,
    ) {}
}
