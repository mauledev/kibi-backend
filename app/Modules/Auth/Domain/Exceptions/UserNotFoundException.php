<?php

namespace App\Modules\Auth\Domain\Exceptions;

class UserNotFoundException extends \Exception
{
    public function __construct(string $message = "Usuario no encontrado")
    {
        parent::__construct($message);
    }
}
