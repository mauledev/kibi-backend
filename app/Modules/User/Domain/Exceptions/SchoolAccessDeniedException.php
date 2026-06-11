<?php

namespace App\Modules\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a non-owner actor requests a school listing for a school they do
 * not have an active assignment in. Mapped to HTTP 403 by the controller.
 */
class SchoolAccessDeniedException extends RuntimeException
{
    public function __construct(string $message = 'No tienes acceso a la escuela solicitada.')
    {
        parent::__construct($message);
    }
}
