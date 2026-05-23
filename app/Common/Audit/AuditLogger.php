<?php

declare(strict_types=1);

namespace App\Common\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Append-only writer for audit_logs.
 * UseCases call this directly — no abstraction needed for MVP.
 */
class AuditLogger implements AuditLoggerInterface
{
    /**
     * Write a single audit log entry.
     *
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
    ): void {
        DB::table('audit_logs')->insert([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'action' => $action,
            'entity_id' => $entityId,
            'struct_before' => $structBefore !== null ? json_encode($structBefore, JSON_THROW_ON_ERROR) : null,
            'struct_after' => $structAfter !== null ? json_encode($structAfter, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
