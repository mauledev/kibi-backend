<?php

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

class InvalidTwoFactorCodeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provided two-factor code is invalid.');
    }
}
