<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * Approving a superadmin request demands a fresh TOTP from the approver, so an
 * approver without a confirmed two-factor enrollment cannot resolve requests.
 */
class ApproverNotTwoFactorEnrolledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'The approver must have a confirmed two-factor enrollment to approve superadmin requests.'
        );
    }
}
