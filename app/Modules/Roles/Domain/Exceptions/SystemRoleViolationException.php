<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class SystemRoleViolationException extends RuntimeException
{
    /** @param string $message Human-readable description of the system role violation. */
    public function __construct(string $message = 'System roles cannot have role_permissions rows; their permissions are fixed in code')
    {
        parent::__construct($message);
    }
}
