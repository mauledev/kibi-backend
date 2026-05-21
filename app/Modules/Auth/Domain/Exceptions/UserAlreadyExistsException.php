<?php

namespace App\Modules\Auth\Domain\Exceptions;

class UserAlreadyExistsException extends \Exception
{
    public function __construct(string $message = "El email ya está registrado")
    {
        parent::__construct($message);
    }
}
