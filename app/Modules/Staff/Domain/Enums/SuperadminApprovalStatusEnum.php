<?php

namespace App\Modules\Staff\Domain\Enums;

/**
 * Lifecycle of a superadmin creation request (dual-control ceremony).
 *
 * pending_approval — proposed by a Superadmin, waiting for a DIFFERENT Superadmin.
 * approved         — second Superadmin confirmed with TOTP; the candidate user exists.
 * rejected         — resolved negatively with a reason; candidate user never created.
 * expired          — expires_at elapsed while pending. Transitioned lazily (no cron):
 *                    persisted when an approve/reject/re-propose touches the row,
 *                    computed on reads via SuperadminApprovalRequest::getEffectiveStatus().
 */
enum SuperadminApprovalStatusEnum: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}
