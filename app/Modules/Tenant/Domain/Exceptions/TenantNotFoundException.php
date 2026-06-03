<?php

namespace App\Modules\Tenant\Domain\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("Tenant with UUID '{$uuid}' was not found.");
    }
}
