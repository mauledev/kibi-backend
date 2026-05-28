<?php

namespace App\Modules\Auth\Domain\Exceptions;

class InvalidCredentialsException extends \Exception
{
    public function __construct(string $message = 'Invalid email or password')
    {
        parent::__construct($message);
    }
}
