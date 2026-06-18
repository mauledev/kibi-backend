<?php

declare(strict_types=1);

use App\Common\Audit\AuditLogger;
use App\Common\Audit\AuditLoggerInterface;
use App\Common\Audit\Events\AuthAuditEvent;
use App\Common\Audit\Events\SchoolAuditEvent;
use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('AuditLogger', function () {
    beforeEach(function () {
        $this->audit = app(AuditLoggerInterface::class);
    });

    it('binds the contract to the append-only writer', function () {
        expect($this->audit)->toBeInstanceOf(AuditLogger::class);
    });

    it('writes a row for a raw string action (backward compatible)', function () {
        $this->audit->log('auth.login', userId: null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login']);
    });

    it('normalizes an AuditEvent enum to its backed value', function () {
        $this->audit->log(AuthAuditEvent::LOGIN_FAILED, userId: null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
        // stores the backed value, never the case name
        $this->assertDatabaseMissing('audit_logs', ['action' => 'LOGIN_FAILED']);
    });

    it('persists actor, entity and JSON snapshots', function () {
        $user = User::factory()->create();

        $this->audit->log(
            SchoolAuditEvent::UPDATE,
            userId: $user->id,
            entityId: 42,
            schoolId: null,
            structBefore: ['name' => 'Old'],
            structAfter: ['name' => 'New', 'active' => true],
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'school.update',
            'user_id' => $user->id,
            'entity_id' => 42,
        ]);

        $row = DB::table('audit_logs')->where('action', 'school.update')->first();
        expect($row)->not->toBeNull();
        expect(json_decode($row->struct_before, true))->toBe(['name' => 'Old']);
        expect(json_decode($row->struct_after, true))->toBe(['name' => 'New', 'active' => true]);
        expect($row->created_at)->not->toBeNull();
    });

    it('leaves JSON snapshots null when not provided', function () {
        $this->audit->log(AuthAuditEvent::LOGIN, userId: null);

        $row = DB::table('audit_logs')->where('action', 'auth.login')->first();
        expect($row->struct_before)->toBeNull();
        expect($row->struct_after)->toBeNull();
    });

    it('appends a new row on every call', function () {
        $this->audit->log(AuthAuditEvent::LOGIN, userId: null);
        $this->audit->log(AuthAuditEvent::LOGOUT, userId: null);

        expect(DB::table('audit_logs')->count())->toBe(2);
    });

    describe('tenant/school attribution from request context', function () {
        it('derives tenant_id from the bound TenantContext when the caller omits it', function () {
            $tenant = Tenant::factory()->create();
            app()->instance(TenantContext::class, new TenantContext(tenantId: $tenant->id));

            $this->audit->log('role.create', userId: null);

            $row = DB::table('audit_logs')->where('action', 'role.create')->first();
            expect($row->tenant_id)->toBe($tenant->id);
        });

        it('derives school_id from the bound SchoolContext when the caller omits it', function () {
            $school = School::factory()->create();
            app()->instance(SchoolContext::class, new SchoolContext(schoolId: $school->id));

            $this->audit->log('student.create', userId: null);

            $row = DB::table('audit_logs')->where('action', 'student.create')->first();
            expect($row->school_id)->toBe($school->id);
        });

        it('lets an explicit argument win over the bound context', function () {
            $contextTenant = Tenant::factory()->create();
            $contextSchool = School::factory()->create();
            $explicitTenant = Tenant::factory()->create();
            $explicitSchool = School::factory()->create();

            app()->instance(TenantContext::class, new TenantContext(tenantId: $contextTenant->id));
            app()->instance(SchoolContext::class, new SchoolContext(schoolId: $contextSchool->id));

            $this->audit->log(
                'payment.approve',
                userId: null,
                schoolId: $explicitSchool->id,
                tenantId: $explicitTenant->id,
            );

            $row = DB::table('audit_logs')->where('action', 'payment.approve')->first();
            expect($row->tenant_id)->toBe($explicitTenant->id);
            expect($row->school_id)->toBe($explicitSchool->id);
        });

        it('leaves tenant_id null when no context is bound (staff route — controlled null)', function () {
            $this->audit->log('superadmin.create', userId: null);

            $row = DB::table('audit_logs')->where('action', 'superadmin.create')->first();
            expect($row->tenant_id)->toBeNull();
            expect($row->school_id)->toBeNull();
        });
    });
});
