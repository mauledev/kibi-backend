<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * A live pending request already exists for the candidate email. Resolve it
 * (approve / reject) or let it expire before proposing again.
 */
class DuplicatePendingApprovalException extends RuntimeException
{
    public function __construct(string $candidateEmail)
    {
        parent::__construct(
            "A pending superadmin approval request already exists for \"{$candidateEmail}\"."
        );
    }
}
