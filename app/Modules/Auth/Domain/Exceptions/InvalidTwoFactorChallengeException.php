<?php

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

class InvalidTwoFactorChallengeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The two-factor challenge is invalid or has expired.');
    }
}
