<?php

namespace App\Modules\Tenant\Domain\Exceptions;

use RuntimeException;

class EmailAlreadyTakenException extends RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct("The email '{$email}' is already registered.");
    }
}
