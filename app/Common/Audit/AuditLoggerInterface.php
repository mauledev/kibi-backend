<?php

namespace App\Common\Audit;

interface AuditLoggerInterface
{
    /**
     * @param  array<string, mixed>|null  $structBefore
     * @param  array<string, mixed>|null  $structAfter
     */
    public function log(
        string $action,
        ?int $userId,
        ?int $entityId = null,
        ?int $schoolId = null,
        ?array $structBefore = null,
        ?array $structAfter = null,
        ?int $tenantId = null,
    ): void;
}
