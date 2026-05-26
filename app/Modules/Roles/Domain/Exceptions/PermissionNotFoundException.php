<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class PermissionNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Permission not found')
    {
        parent::__construct($message);
    }
}
