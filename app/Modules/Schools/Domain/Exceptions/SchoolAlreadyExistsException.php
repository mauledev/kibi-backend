<?php

namespace App\Modules\Schools\Domain\Exceptions;

final class SchoolAlreadyExistsException extends SchoolException
{
    public static function withSlug(string $slug, int $tenantId): self
    {
        return new self(
            "School with slug '{$slug}' already exists in tenant {$tenantId}"
        );
    }
}
