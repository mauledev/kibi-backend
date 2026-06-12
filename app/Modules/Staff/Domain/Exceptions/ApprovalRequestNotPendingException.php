<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * The request was already resolved (approved / rejected / expired) — only
 * pending_approval requests can transition.
 */
class ApprovalRequestNotPendingException extends RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct(
            "The superadmin approval request is already resolved (status: {$status})."
        );
    }
}
