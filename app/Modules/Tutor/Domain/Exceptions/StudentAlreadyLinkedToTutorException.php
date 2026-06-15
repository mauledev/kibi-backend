<?php

namespace App\Modules\Tutor\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an attempt is made to create a tutor-student link that already exists and is active.
 *
 * HTTP mapping: 409 Conflict.
 */
class StudentAlreadyLinkedToTutorException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This student is already linked to this tutor.');
    }
}
