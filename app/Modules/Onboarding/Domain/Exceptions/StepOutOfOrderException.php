<?php

namespace App\Modules\Onboarding\Domain\Exceptions;

use RuntimeException;

class StepOutOfOrderException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cannot complete a step out of order');
    }
}
