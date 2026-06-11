<?php

namespace App\Modules\Onboarding\Domain\Exceptions;

use RuntimeException;

class SchoolNotInTenantException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('School not found in your tenant');
    }
}
