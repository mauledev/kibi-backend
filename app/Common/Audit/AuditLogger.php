<?php

declare(strict_types=1);

namespace App\Common\Audit;

use App\Common\Audit\Events\AuditEvent;
use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
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
     * Tenant/school attribution is derived from the per-request container context
     * (TenantContext/SchoolContext, bound by the tenant/school middleware) when the
     * caller does not pass them explicitly. This centralizes derivation so no caller
     * can forget to attribute an action to its institution. An explicit argument always
     * wins; on staff routes (no TenantContext) both stay null — a controlled null.
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
        ?int $tenantId = null,
    ): void {
        $tenantId ??= $this->resolveTenantId();
        $schoolId ??= $this->resolveSchoolId();

        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'user_id' => $userId,
            'action' => is_string($action) ? $action : (string) $action->value,
            'entity_id' => $entityId,
            'struct_before' => $structBefore !== null ? json_encode($structBefore, JSON_THROW_ON_ERROR) : null,
            'struct_after' => $structAfter !== null ? json_encode($structAfter, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Derive the current tenant from the request-scoped container binding.
     * Returns null on contexts where no tenant is bound (e.g. staff routes).
     */
    private function resolveTenantId(): ?int
    {
        return app()->bound(TenantContext::class)
            ? app(TenantContext::class)->tenantId
            : null;
    }

    /**
     * Derive the current school from the request-scoped container binding.
     * Returns null on tenant-level requests where no school header was sent.
     */
    private function resolveSchoolId(): ?int
    {
        return app()->bound(SchoolContext::class)
            ? app(SchoolContext::class)->schoolId
            : null;
    }
}
