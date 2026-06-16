<?php

namespace App\Modules\Staff\Application\UseCases\ProposeSuperadminCreation;

class ProposeSuperadminCreationInput
{
    public function __construct(
        public readonly string $justification,
        public readonly string $candidateEmail,
        public readonly string $candidateFirstName,
        public readonly string $candidateLastNamePaternal,
        public readonly ?string $candidateLastNameMaternal,
        public readonly ?string $candidatePhone,
        public readonly int $proposedBy,
    ) {}
}
