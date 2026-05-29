<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class OwnerRoleAssignmentException extends RuntimeException
{
    public function __construct(string $message = 'The owner role cannot be assigned via role assignments')
    {
        parent::__construct($message);
    }
}
