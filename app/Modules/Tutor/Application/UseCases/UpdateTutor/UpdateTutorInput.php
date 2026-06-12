<?php

namespace App\Modules\Tutor\Application\UseCases\UpdateTutor;

/**
 * Input DTO for UpdateTutorUseCase.
 */
final class UpdateTutorInput
{
    public function __construct(
        public readonly string $userUuid,
        public readonly ?string $firstName = null,
        public readonly ?string $lastNamePaternal = null,
        public readonly ?string $lastNameMaternal = null,
        public readonly ?string $phone = null,
        public readonly ?string $occupation = null,
    ) {}
}
