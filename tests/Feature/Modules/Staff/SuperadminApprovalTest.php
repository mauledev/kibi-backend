<?php

use App\Mail\OwnerActivationMail;
use App\Models\Role;
use App\Models\SuperadminApprovalRequest as ApprovalModel;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

/**
 * NOTE: helper names are intentionally unique to this file — Pest test files
 * declare plain global functions, and makeSuperadmin()/validCreatePersonnelPayload()
 * (CreatePersonnelTest) and enrollStaffTwoFactor() (TwoFactorLoginTest) already
 * exist; redeclaring them fatals the full-suite run.
 */

/** Create a staff user holding the seeded Softlinkia superadmin role. */
function makeApprovalSuperadmin(string $email): User
{
    $user = User::factory()->staff()->create(['email' => $email]);

    $role = Role::where('slug', 'superadmin')->whereNull('tenant_id')->firstOrFail();

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'school_id' => null,
        'assigned_at' => now(),
    ]);

    acceptPurFor($user);

    return $user;
}

/**
 * Mark a superadmin as enrolled in 2FA with a known secret.
 *
 * @return string The plaintext TOTP secret (feed it to Google2FA::getCurrentOtp).
 */
function enrollApproverTotp(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

/**
 * Valid payload for proposing a superadmin creation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validProposalPayload(array $overrides = []): array
{
    $base = [
        'justification' => 'Replacing the departing platform lead before Q3 audit.',
        'personal_data' => [
            'first_name' => 'Ana',
            'last_name_paternal' => 'Lopez',
            'last_name_maternal' => null,
            'email' => 'ana.lopez@softlinkia.com',
            'phone' => null,
        ],
    ];

    if (isset($overrides['personal_data'])) {
        $overrides['personal_data'] = array_merge($base['personal_data'], $overrides['personal_data']);
    }

    return array_merge($base, $overrides);
}

/** Propose through the API and return the request uuid. */
function proposeSuperadminApproval(User $proposer, array $overrides = []): string
{
    return test()->actingAs($proposer)
        ->postJson('/api/staff/superadmin/approvals', validProposalPayload($overrides))
        ->assertStatus(201)
        ->json('data.id');
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();

    $this->proposer = makeApprovalSuperadmin('proposer@softlinkia.com');
    $this->approver = makeApprovalSuperadmin('approver@softlinkia.com');
});

describe('POST /api/staff/superadmin/approvals (propose)', function () {
    it('returns 401 when unauthenticated', function () {
        $this->postJson('/api/staff/superadmin/approvals', validProposalPayload())
            ->assertStatus(401);
    });

    it('returns 403 and audits the attempt when the staff user is not superadmin', function () {
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($plainStaff)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload())
            ->assertStatus(403);

        $this->assertDatabaseMissing('superadmin_approval_requests', [
            'candidate_email' => 'ana.lopez@softlinkia.com',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.access_denied',
            'user_id' => $plainStaff->id,
        ]);
    });

    it('returns 201 with a pending request and creates NO user', function () {
        $response = $this->actingAs($this->proposer)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.personal_data.email', 'ana.lopez@softlinkia.com')
            ->assertJsonPath('data.proposed_by.email', 'proposer@softlinkia.com')
            ->assertJsonPath('data.resolved_by', null)
            ->assertJsonPath('data.created_user_id', null)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'justification',
                    'personal_data' => ['first_name', 'last_name_paternal', 'email', 'phone'],
                    'proposed_by' => ['id', 'full_name', 'email'],
                    'expires_at',
                    'created_at',
                ],
            ]);

        // The ceremony defers account creation to the approval step.
        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
        Mail::assertNothingSent();

        // 72h expiry window.
        $expiresAt = new DateTimeImmutable($response->json('data.expires_at'));
        expect($expiresAt > new DateTimeImmutable('+71 hours'))->toBeTrue()
            ->and($expiresAt < new DateTimeImmutable('+73 hours'))->toBeTrue();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'superadmin_approval.propose',
            'user_id' => $this->proposer->id,
        ]);
    });

    it('returns 409 when a pending request already exists for the candidate email', function () {
        proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload())
            ->assertStatus(409);

        expect(ApprovalModel::where('candidate_email', 'ana.lopez@softlinkia.com')->count())->toBe(1);
    });

    it('returns 409 when the candidate email already belongs to a user', function () {
        User::factory()->create(['email' => 'ana.lopez@softlinkia.com']);

        $this->actingAs($this->proposer)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload())
            ->assertStatus(409);
    });

    it('returns 422 when justification or candidate email are invalid', function () {
        $this->actingAs($this->proposer)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload(['justification' => 'short']))
            ->assertStatus(422);

        $this->actingAs($this->proposer)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload([
                'personal_data' => ['email' => 'not-an-email'],
            ]))
            ->assertStatus(422);
    });

    it('allows re-proposing after the previous pending request expired', function () {
        $firstUuid = proposeSuperadminApproval($this->proposer);

        ApprovalModel::where('uuid', $firstUuid)->update(['expires_at' => now()->subHour()]);

        $this->actingAs($this->proposer)
            ->postJson('/api/staff/superadmin/approvals', validProposalPayload())
            ->assertStatus(201);

        // The stale request was lazily transitioned to release the unique slot.
        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $firstUuid,
            'status' => 'expired',
        ]);
    });
});

describe('POST /api/staff/superadmin/approvals/{uuid}/approve', function () {
    it('creates the superadmin when a different superadmin approves with a valid TOTP', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $secret = enrollApproverTotp($this->approver);

        $response = $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.resolved_by.email', 'approver@softlinkia.com');

        // Pending account: no password, activation pending.
        $this->assertDatabaseHas('users', [
            'email' => 'ana.lopez@softlinkia.com',
            'is_staff' => true,
            'tenant_id' => null,
            'password_hash' => null,
            'email_verified_at' => null,
        ]);

        $created = User::where('email', 'ana.lopez@softlinkia.com')->firstOrFail();

        expect($response->json('data.created_user_id'))->toBe($created->uuid);

        $hasRole = UserRoleAssignment::where('user_id', $created->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'superadmin'))
            ->whereNull('revoked_at')
            ->exists();

        expect($hasRole)->toBeTrue();

        Mail::assertSent(OwnerActivationMail::class);

        // CRITICAL audit row carries both signatures (dual control evidence).
        $auditRow = DB::table('audit_logs')->where('action', 'superadmin.create')->first();

        expect($auditRow)->not->toBeNull();

        $struct = json_decode((string) $auditRow->struct_after, true);

        expect($struct['severity'])->toBe('CRITICAL')
            ->and($struct['proposed_by'])->toBe($this->proposer->id)
            ->and($struct['approved_by'])->toBe($this->approver->id)
            ->and($struct['user_uuid'])->toBe($created->uuid)
            ->and($struct['role'])->toBe('superadmin');
    });

    it('returns 403 when the proposer tries to approve their own request', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $secret = enrollApproverTotp($this->proposer);

        $this->actingAs($this->proposer)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'pending_approval',
        ]);
    });

    it('returns 422 for an invalid TOTP and keeps the request pending', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        enrollApproverTotp($this->approver);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", ['code' => '000000'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'pending_approval',
        ]);
        Mail::assertNothingSent();
    });

    it('returns 422 when the approver has no confirmed 2FA enrollment', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", ['code' => '123456'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
    });

    it('returns 409 when approving an already approved request and creates no duplicate', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $secret = enrollApproverTotp($this->approver);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertStatus(200);

        $second = makeApprovalSuperadmin('third@softlinkia.com');
        $secondSecret = enrollApproverTotp($second);

        $this->actingAs($second)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secondSecret),
            ])
            ->assertStatus(409);

        expect(User::where('email', 'ana.lopez@softlinkia.com')->count())->toBe(1);
    });

    it('returns 409 when approving a rejected request', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", [
                'reason' => 'Not justified enough.',
            ])
            ->assertStatus(200);

        $secret = enrollApproverTotp($this->approver);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertStatus(409);
    });

    it('returns 409 for an expired request and persists the lazy transition', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $secret = enrollApproverTotp($this->approver);

        ApprovalModel::where('uuid', $uuid)->update(['expires_at' => now()->subHour()]);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'expired',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
    });

    it('returns 409 when the candidate email was taken between proposal and approval', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $secret = enrollApproverTotp($this->approver);

        User::factory()->create(['email' => 'ana.lopez@softlinkia.com']);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertStatus(409);

        // The request stays pending so the operator can reject it with a reason.
        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'pending_approval',
        ]);
    });

    it('returns 404 for an unknown request uuid', function () {
        enrollApproverTotp($this->approver);

        $this->actingAs($this->approver)
            ->postJson('/api/staff/superadmin/approvals/'.Str::uuid().'/approve', ['code' => '123456'])
            ->assertStatus(404);
    });

    it('returns 403 and audits the attempt for a non-superadmin caller', function () {
        $uuid = proposeSuperadminApproval($this->proposer);
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($plainStaff)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/approve", ['code' => '123456'])
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.access_denied',
            'user_id' => $plainStaff->id,
        ]);
    });
});

describe('POST /api/staff/superadmin/approvals/{uuid}/reject', function () {
    it('rejects a pending request with a reason and no TOTP', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $response = $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", [
                'reason' => 'Scope is unclear; resubmit with tenant detail.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Scope is unclear; resubmit with tenant detail.')
            ->assertJsonPath('data.resolved_by.email', 'approver@softlinkia.com');

        $this->assertDatabaseMissing('users', ['email' => 'ana.lopez@softlinkia.com']);
        Mail::assertNothingSent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'superadmin_approval.reject',
            'user_id' => $this->approver->id,
        ]);
    });

    it('returns 403 when the proposer tries to reject their own request', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->proposer)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", [
                'reason' => 'Changed my mind about this.',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'pending_approval',
        ]);
    });

    it('returns 422 when the reason is missing', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", [])
            ->assertStatus(422);
    });

    it('returns 409 when rejecting an already resolved request', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", ['reason' => 'First rejection here.'])
            ->assertStatus(200);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", ['reason' => 'Second rejection try.'])
            ->assertStatus(409);
    });

    it('returns 409 for an expired request and persists the lazy transition', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        ApprovalModel::where('uuid', $uuid)->update(['expires_at' => now()->subHour()]);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$uuid}/reject", ['reason' => 'Too late anyway.'])
            ->assertStatus(409);

        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'expired',
        ]);
    });

    it('returns 404 for an unknown request uuid', function () {
        $this->actingAs($this->approver)
            ->postJson('/api/staff/superadmin/approvals/'.Str::uuid().'/reject', ['reason' => 'Whatever reason.'])
            ->assertStatus(404);
    });
});

describe('GET /api/staff/superadmin/approvals (list + show)', function () {
    it('lists requests paginated with meta.pagination', function () {
        proposeSuperadminApproval($this->proposer);
        proposeSuperadminApproval($this->proposer, [
            'personal_data' => ['email' => 'bruno.diaz@softlinkia.com'],
        ]);

        $response = $this->actingAs($this->approver)
            ->getJson('/api/staff/superadmin/approvals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'last_page']],
            ])
            ->assertJsonPath('meta.pagination.total', 2);
    });

    it('reports expired status on reads without writing the row', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        ApprovalModel::where('uuid', $uuid)->update(['expires_at' => now()->subHour()]);

        $this->actingAs($this->approver)
            ->getJson("/api/staff/superadmin/approvals/{$uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'expired');

        // Reads are lazy: the stored row is untouched until a write path hits it.
        $this->assertDatabaseHas('superadmin_approval_requests', [
            'uuid' => $uuid,
            'status' => 'pending_approval',
        ]);
    });

    it('filters by status', function () {
        proposeSuperadminApproval($this->proposer);
        $rejectedUuid = proposeSuperadminApproval($this->proposer, [
            'personal_data' => ['email' => 'bruno.diaz@softlinkia.com'],
        ]);

        $this->actingAs($this->approver)
            ->postJson("/api/staff/superadmin/approvals/{$rejectedUuid}/reject", ['reason' => 'Not needed right now.'])
            ->assertStatus(200);

        $this->actingAs($this->approver)
            ->getJson('/api/staff/superadmin/approvals?status=pending_approval')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.status', 'pending_approval');

        $this->actingAs($this->approver)
            ->getJson('/api/staff/superadmin/approvals?status=rejected')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $rejectedUuid);
    });

    it('shows a single request and 404s on unknown uuid', function () {
        $uuid = proposeSuperadminApproval($this->proposer);

        $this->actingAs($this->approver)
            ->getJson("/api/staff/superadmin/approvals/{$uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.personal_data.email', 'ana.lopez@softlinkia.com');

        $this->actingAs($this->approver)
            ->getJson('/api/staff/superadmin/approvals/'.Str::uuid())
            ->assertStatus(404);
    });

    it('returns 403 for a non-superadmin caller', function () {
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($plainStaff)
            ->getJson('/api/staff/superadmin/approvals')
            ->assertStatus(403);
    });
});
