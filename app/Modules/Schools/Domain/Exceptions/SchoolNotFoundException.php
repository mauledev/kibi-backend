<?php

namespace App\Modules\Schools\Domain\Exceptions;

final class SchoolNotFoundException extends SchoolException
{
    public static function withUuid(string $uuid): self
    {
        return new self("School with uuid '{$uuid}' not found");
    }

    public static function withUuidInTenant(string $uuid, int $tenantId): self
    {
        return new self(
            "School with uuid '{$uuid}' not found in tenant {$tenantId}"
        );
    }
}
