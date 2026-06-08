<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class AssignmentNotFoundException extends RuntimeException
{
    /** @param string $message Human-readable description of the missing assignment. */
    public function __construct(string $message = 'Role assignment not found')
    {
        parent::__construct($message);
    }
}
