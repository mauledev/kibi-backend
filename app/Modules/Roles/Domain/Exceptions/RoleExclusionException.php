<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class RoleExclusionException extends RuntimeException
{
    public function __construct(string $message = 'The role cannot be assigned because it conflicts with an existing role for this user in the same school.')
    {
        parent::__construct($message);
    }
}
