<?php

namespace App\Modules\Schools\Domain\Exceptions;

final class InvalidSchoolStatusException extends SchoolException
{
    public static function transition(string $from, string $to): self
    {
        return new self(
            "Invalid school status transition from '{$from}' to '{$to}'"
        );
    }

    public static function unknownStatus(string $status): self
    {
        return new self("Unknown school status: '{$status}'");
    }
}
