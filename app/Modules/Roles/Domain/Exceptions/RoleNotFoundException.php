<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class RoleNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Role not found')
    {
        parent::__construct($message);
    }
}
