<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class CustomRoleLimitExceededException extends RuntimeException
{
    public function __construct(string $message = 'The tenant custom role limit has been reached.')
    {
        parent::__construct($message);
    }
}
