<?php

namespace App\Modules\Tutor\Application\UseCases\LinkTutorToStudent;

/**
 * Input DTO for LinkTutorToStudentUseCase.
 */
final class LinkTutorToStudentInput
{
    public function __construct(
        public readonly string $tutorUserUuid,
        public readonly string $studentUserUuid,
        public readonly ?string $relationship,
        public readonly string $tenantSlug,
    ) {}
}
