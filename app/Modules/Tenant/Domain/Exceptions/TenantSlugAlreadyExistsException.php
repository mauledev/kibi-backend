<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Domain\Exceptions;

use RuntimeException;

class TenantSlugAlreadyExistsException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("The slug '{$slug}' is already taken by another tenant.");
    }
}
