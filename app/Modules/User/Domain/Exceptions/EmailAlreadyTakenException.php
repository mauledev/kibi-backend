<?php

namespace App\Modules\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when inviting a user whose email already exists anywhere in the
 * users table (the check is global, not tenant-scoped).
 */
class EmailAlreadyTakenException extends RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct("The email {$email} is already registered.");
    }
}
