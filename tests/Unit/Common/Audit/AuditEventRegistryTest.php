<?php

declare(strict_types=1);

use App\Common\Audit\Events\AcademicAuditEvent;
use App\Common\Audit\Events\AuditEvent;
use App\Common\Audit\Events\AuditEventRegistry;
use App\Common\Audit\Events\AuthAuditEvent;
use App\Common\Audit\Events\RoleAuditEvent;
use App\Common\Audit\Events\SchoolAuditEvent;
use App\Common\Audit\Events\TenantAuditEvent;
use App\Common\Audit\Events\TreasuryAuditEvent;

describe('AuditEventRegistry', function () {
    it('registers at least one event for every module', function () {
        expect(AuditEventRegistry::modules())->not->toBeEmpty();

        foreach (AuditEventRegistry::modules() as $enum) {
            expect($enum::cases())->not->toBeEmpty();
        }
    });

    it('exposes every catalog entry as an AuditEvent', function () {
        foreach (AuditEventRegistry::all() as $event) {
            expect($event)->toBeInstanceOf(AuditEvent::class);
        }
    });

    it('follows the {model}.{verb} naming convention', function () {
        foreach (AuditEventRegistry::actions() as $action) {
            expect($action)->toMatch('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/');
        }
    });

    it('has globally unique action strings', function () {
        $actions = AuditEventRegistry::actions();

        expect($actions)->toHaveCount(count(array_unique($actions)));
    });

    it('defines at least three events for each critical module', function () {
        $critical = [
            AuthAuditEvent::class,
            SchoolAuditEvent::class,
            TenantAuditEvent::class,
            RoleAuditEvent::class,
            AcademicAuditEvent::class,
            TreasuryAuditEvent::class,
        ];

        foreach ($critical as $enum) {
            expect(count($enum::cases()))->toBeGreaterThanOrEqual(3);
        }
    });

    it('keeps every action already written by live UseCases', function () {
        // Guard: the catalog must stay a superset of what production code logs today.
        // Only Roles UseCases emit audit events at the time of writing; other modules
        // will expand this list as they are implemented.
        $existing = [
            'role.create',
            'role.update',
            'role.delete',
            'role.assign',
            'role.revoke',
            'permission.grant',
            'permission.revoke',
        ];

        $catalog = AuditEventRegistry::actions();

        foreach ($existing as $action) {
            expect($catalog)->toContain($action);
        }
    });
});
