<?php

namespace App\Modules\Auth\Domain\Exceptions;

class InvalidCredentialsException extends \Exception
{
    public function __construct(string $message = "Email o contraseña incorrectos")
    {
        parent::__construct($message);
    }
}
