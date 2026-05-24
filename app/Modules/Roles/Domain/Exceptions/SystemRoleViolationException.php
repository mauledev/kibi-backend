<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class SystemRoleViolationException extends RuntimeException
{
    public function __construct(string $message = 'System roles cannot have role_permissions rows; their permissions are fixed in code')
    {
        parent::__construct($message);
    }
}
