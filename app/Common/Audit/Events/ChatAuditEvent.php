<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Auditable events for the Chat module — moderation only.
 *
 * Message content and routine delivery are NOT audited (see docs/audit.md);
 * only moderation actions reach the audit trail.
 */
enum ChatAuditEvent: string implements AuditEvent
{
    case MESSAGE_FLAG = 'message.flag';
    case CONVERSATION_ARCHIVE = 'conversation.archive';
}
