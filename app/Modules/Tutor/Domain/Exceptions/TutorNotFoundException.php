<?php

namespace App\Modules\Tutor\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a tutor cannot be found by the given identifier within the current tenant scope.
 */
class TutorNotFoundException extends RuntimeException
{
    public function __construct(string $identifier = '')
    {
        $message = $identifier !== ''
            ? "Tutor not found: {$identifier}"
            : 'Tutor not found.';

        parent::__construct($message);
    }
}
