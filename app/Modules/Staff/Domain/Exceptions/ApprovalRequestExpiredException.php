<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

class ApprovalRequestExpiredException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct(
            "Superadmin approval request \"{$uuid}\" has expired. Propose it again if still needed."
        );
    }
}
