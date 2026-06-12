<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

class ApprovalRequestNotFoundException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("Superadmin approval request \"{$uuid}\" was not found.");
    }
}
