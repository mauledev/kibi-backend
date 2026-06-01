<?php

namespace App\Modules\Schools\Application\UseCases\UpdateSchool;

/**
 * Partial-update input. Each `has*` flag indicates whether the corresponding
 * field was present in the request — null is a valid update value for phone
 * and address, so absence must be distinguishable from a null value.
 */
final readonly class UpdateSchoolInput
{
    /**
     * @param  array<string, mixed>|null  $address
     */
    public function __construct(
        public int $actorUserId,
        public string $uuid,
        public bool $hasName = false,
        public ?string $name = null,
        public bool $hasPhone = false,
        public ?string $phone = null,
        public bool $hasAddress = false,
        public ?array $address = null,
    ) {}
}
