<?php

namespace App\Modules\Student\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a student cannot be found within the current tenant scope.
 *
 * The controller maps this exception to a 404 Not Found response.
 */
class StudentNotFoundException extends RuntimeException
{
    /** @param string|null $uuid The UUID that was searched, or null when unknown. */
    public function __construct(?string $uuid = null)
    {
        $message = $uuid !== null
            ? "Student with UUID '{$uuid}' not found."
            : 'Student not found.';

        parent::__construct($message);
    }
}
