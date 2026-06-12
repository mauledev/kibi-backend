<?php

namespace App\Modules\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a user lookup by UUID returns no result within the current tenant scope.
 */
class UserNotFoundException extends RuntimeException
{
    /**
     * Create an exception for a UUID-based lookup failure.
     */
    public function __construct(string $uuid)
    {
        parent::__construct("User not found: {$uuid}");
    }
}
