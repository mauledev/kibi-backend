<?php

namespace App\Modules\Auth\Domain\Exceptions;

use RuntimeException;

class TwoFactorNotEnrolledException extends RuntimeException
{
    public function __construct(int $userId)
    {
        parent::__construct("User {$userId} has no pending two-factor secret to confirm.");
    }
}
