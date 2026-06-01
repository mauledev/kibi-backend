<?php

namespace App\Modules\Schools\Application\UseCases\CreateSchool;

final readonly class CreateSchoolInput
{
    /**
     * @param  array<string, mixed>|null  $address
     */
    public function __construct(
        public int $actorUserId,
        public string $name,
        public string $slug,
        public ?array $address = null,
        public ?string $phone = null,
    ) {}
}
