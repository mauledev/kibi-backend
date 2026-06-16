<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

class StaffEmailAlreadyTakenException extends RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct("The email \"{$email}\" is already registered.");
    }
}
