<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class PermissionNotFoundException extends RuntimeException
{
    /** @param string $message Human-readable description of the missing permission. */
    public function __construct(string $message = 'Permission not found')
    {
        parent::__construct($message);
    }
}
