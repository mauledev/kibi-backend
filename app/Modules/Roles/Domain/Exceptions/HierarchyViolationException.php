<?php

namespace App\Modules\Roles\Domain\Exceptions;

use RuntimeException;

class HierarchyViolationException extends RuntimeException
{
    /** @param string $message Human-readable description of the hierarchy violation. */
    public function __construct(string $message = 'Hierarchy violation: you can only manage roles with a higher hierarchy level than your own')
    {
        parent::__construct($message);
    }
}
