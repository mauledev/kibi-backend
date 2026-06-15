<?php

namespace App\Modules\Tutor\Domain\ValueObjects;

/**
 * Carries the mutable fields for a tutor update operation.
 *
 * All fields are nullable — only non-null values are applied on update.
 */
final class TutorUpdateData
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastNamePaternal = null,
        public readonly ?string $lastNameMaternal = null,
        public readonly ?string $phone = null,
        public readonly ?string $occupation = null,
    ) {}
}
