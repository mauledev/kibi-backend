<?php

namespace App\Modules\Tutor\Application\UseCases\GetTutor;

/**
 * Input DTO for GetTutorUseCase.
 */
final class GetTutorInput
{
    public function __construct(
        public readonly string $userUuid,
    ) {}
}
