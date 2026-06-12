<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class CustomRoleLimitExceededException extends RuntimeException
{
    /** @param string $message Human-readable description of the limit violation. */
    public function __construct(string $message = 'The tenant custom role limit has been reached.')
    {
        parent::__construct($message);
    }
}
