<?php

namespace App\Modules\User\Application\UseCases\GetUser;

/**
 * Input DTO for the GetUser use case.
 *
 * Carries the public UUID used to look up a single tenant user.
 * Tenant context is resolved internally by the repository.
 */
final readonly class GetUserInput
{
    public function __construct(
        public string $uuid,
    ) {}
}
