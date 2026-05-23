<?php

declare(strict_types=1);

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class AssignmentNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Role assignment not found')
    {
        parent::__construct($message);
    }
}
