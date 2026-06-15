<?php

namespace App\Modules\Tutor\Application\UseCases\ListTutors;

/**
 * Input DTO for ListTutorsUseCase.
 */
final class ListTutorsInput
{
    /**
     * @param  array<int, int>  $accessibleSchoolIds
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly bool $isOwner = false,
        public readonly array $accessibleSchoolIds = [],
        public readonly ?int $requestedSchoolId = null,
        public readonly int $perPage = 20,
        public readonly int $page = 1,
    ) {}
}
