<?php

namespace App\Common\Audit;

use App\Common\Audit\Events\AuditEvent;

interface AuditLoggerInterface
{
    /**
     * Write a single audit log entry.
     *
     * @param  AuditEvent|string  $action  A catalog event (preferred) or a raw {model}.{verb} string
     * @param  array<string, mixed>|null  $structBefore
     * @param  array<string, mixed>|null  $structAfter
     */
    public function log(
        AuditEvent|string $action,
        ?int $userId,
        ?int $entityId = null,
        ?int $schoolId = null,
        ?array $structBefore = null,
        ?array $structAfter = null,
    ): void;
}
