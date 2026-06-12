<?php

declare(strict_types=1);

namespace App\Common\Audit\Events;

/**
 * Central registry of every auditable event across all modules.
 *
 * Single source of truth that aggregates the per-module enums. Consumed by the
 * coverage tests today and by the future audit admin/export endpoints (E-12).
 * To register a new module's events: create its enum implementing AuditEvent
 * and add it to modules().
 */
class AuditEventRegistry
{
    /**
     * Every registered per-module audit event enum class.
     *
     * @return list<class-string<AuditEvent>>
     */
    public static function modules(): array
    {
        return [
            AuthAuditEvent::class,
            OnboardingAuditEvent::class,
            SchoolAuditEvent::class,
            TenantAuditEvent::class,
            RoleAuditEvent::class,
            AcademicAuditEvent::class,
            ChatAuditEvent::class,
            TreasuryAuditEvent::class,
            PaymentsAuditEvent::class,
            NotificationsAuditEvent::class,
            HardwareAuditEvent::class,
            DunningAuditEvent::class,
            StudentAuditEvent::class,
            ImpersonationAuditEvent::class,
            ReportAuditEvent::class,
        ];
    }

    /**
     * Every audit event case across all registered modules.
     *
     * @return list<AuditEvent>
     */
    public static function all(): array
    {
        $events = [];

        foreach (self::modules() as $enum) {
            foreach ($enum::cases() as $case) {
                $events[] = $case;
            }
        }

        return $events;
    }

    /**
     * Every audit action string across all registered modules.
     *
     * @return list<string>
     */
    public static function actions(): array
    {
        return array_map(
            static fn (AuditEvent $event): string => (string) $event->value,
            self::all(),
        );
    }
}
