<?php

namespace App\Modules\Tutor\Application\UseCases\CreateTutor;

/**
 * Input DTO for CreateTutorUseCase.
 */
final class CreateTutorInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantSlug,
        public readonly string $actorUuid,
        public readonly string $actorSlug,
        public readonly string $schoolUuid,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly ?string $phone,
        public readonly ?string $occupation,
    ) {}
}
