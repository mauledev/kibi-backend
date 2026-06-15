<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * Dual control: the Superadmin who proposed a request cannot resolve it
 * (approve or reject) — a second, distinct Superadmin must.
 */
class SelfApprovalForbiddenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The proposer cannot resolve their own superadmin approval request.');
    }
}
