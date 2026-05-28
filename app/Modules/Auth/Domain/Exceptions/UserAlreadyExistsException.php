<?php

namespace App\Modules\Auth\Domain\Exceptions;

class UserAlreadyExistsException extends \Exception
{
    public function __construct(string $message = 'Email already registered')
    {
        parent::__construct($message);
    }
}
