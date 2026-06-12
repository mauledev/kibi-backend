<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class RoleNotFoundException extends RuntimeException
{
    /** @param string $message Human-readable description of the missing role. */
    public function __construct(string $message = 'Role not found')
    {
        parent::__construct($message);
    }
}
