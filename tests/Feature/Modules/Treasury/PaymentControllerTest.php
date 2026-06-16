<?php

use App\Models\Payment as PaymentModel;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PaymentController (staff)', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->schoolA = SchoolModel::factory()->forTenant($this->tenantA)->create();
        $this->schoolB = SchoolModel::factory()->forTenant($this->tenantB)->create();
        $this->staff = User::factory()->staff()->create();
    });

    describe('GET /api/staff/treasury/payments', function () {
        it('returns 401 when unauthenticated', function () {
            $this->getJson('/api/staff/treasury/payments')->assertStatus(401);
        });

        it('returns 403 when the authenticated user is not staff', function () {
            $tenantUser = User::factory()->for($this->tenantA)->create();

            $this->actingAs($tenantUser)
                ->getJson('/api/staff/treasury/payments')
                ->assertStatus(403);
        });

        it('returns 200 with the expected envelope and pagination fields', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(3)->create();

            $response = $this->actingAs($this->staff)->getJson('/api/staff/treasury/payments');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success', 'status', 'data' => ['data', 'total', 'page', 'per_page'],
                ]);

            expect($response->json('data.total'))->toBe(3);
            expect($response->json('data.per_page'))->toBe(25);
        });

        it('returns payments from every tenant by default (no tenant scope)', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->count(2)->create();
            PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->count(3)->create();

            $response = $this->actingAs($this->staff)->getJson('/api/staff/treasury/payments');

            expect($response->json('data.total'))->toBe(5);
        });

        it('each item carries both company_name and school_name', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $response = $this->actingAs($this->staff)->getJson('/api/staff/treasury/payments');

            $item = $response->json('data.data.0');
            expect($item)->toHaveKey('company_name');
            expect($item)->toHaveKey('school_name');
            expect($item['company_name'])->toBe($this->tenantA->name);
            expect($item['school_name'])->toBe($this->schoolA->name);
        });

        it('filters by ?status', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->approved()->create();

            $response = $this->actingAs($this->staff)->getJson('/api/staff/treasury/payments?status=approved');

            expect($response->json('data.total'))->toBe(1);
        });

        it('filters by ?company_id (tenant UUID)', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();
            PaymentModel::factory()->forTenant($this->tenantB)->forSchool($this->schoolB)->count(2)->create();

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/treasury/payments?company_id='.$this->tenantA->uuid);

            expect($response->json('data.total'))->toBe(1);
        });

        it('returns empty result when ?company_id points to an unknown UUID', function () {
            PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $response = $this->actingAs($this->staff)
                ->getJson('/api/staff/treasury/payments?company_id=00000000-0000-0000-0000-000000000000');

            expect($response->json('data.total'))->toBe(0);
        });

        it('returns 422 on invalid ?status', function () {
            $this->actingAs($this->staff)
                ->getJson('/api/staff/treasury/payments?status=foo')
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        });

        it('returns 422 when date_to is before date_from', function () {
            $this->actingAs($this->staff)
                ->getJson('/api/staff/treasury/payments?date_from=2026-06-10&date_to=2026-06-01')
                ->assertStatus(422)
                ->assertJsonValidationErrors(['date_to']);
        });
    });

    describe('GET /api/staff/treasury/payments/{uuid}', function () {
        it('returns 200 with the detail (payment + state_log)', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $response = $this->actingAs($this->staff)
                ->getJson("/api/staff/treasury/payments/{$payment->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['uuid', 'status', 'company_name', 'school_name', 'payer_name', 'amount_cents', 'state_log'],
                ]);
        });

        it('returns 404 when the UUID does not exist', function () {
            $this->actingAs($this->staff)
                ->getJson('/api/staff/treasury/payments/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });
    });

    describe('POST /api/staff/treasury/payments/{uuid}/approve', function () {
        it('returns 200 and transitions the payment to approved', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $response = $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/approve", [
                    'amount_received_cents' => 150_000,
                    'note' => 'OK conciliado',
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.status', 'approved');

            $this->assertDatabaseHas('payments', [
                'id' => $payment->id,
                'status' => 'approved',
                'received_amount_cents' => 150_000,
            ]);
        });

        it('writes a state_log entry on approval', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/approve", [
                    'amount_received_cents' => 150_000,
                ])
                ->assertStatus(200);

            $this->assertDatabaseHas('payment_state_transitions', [
                'payment_id' => $payment->id,
                'event' => 'approved',
                'from_status' => 'pending',
                'to_status' => 'approved',
                'actor_user_id' => $this->staff->id,
            ]);
        });

        it('returns 409 when the payment is already approved', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->approved()->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/approve", [
                    'amount_received_cents' => 150_000,
                ])
                ->assertStatus(409);
        });

        it('returns 422 when amount_received_cents is missing', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/approve", [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['amount_received_cents']);
        });

        it('returns 403 when the authenticated user is not staff', function () {
            $tenantUser = User::factory()->for($this->tenantA)->create();
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($tenantUser)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/approve", [
                    'amount_received_cents' => 100,
                ])
                ->assertStatus(403);
        });
    });

    describe('POST /api/staff/treasury/payments/{uuid}/reject', function () {
        it('returns 200 and transitions the payment to rejected', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $response = $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/reject", [
                    'reason' => 'amount_mismatch',
                    'note' => 'Faltaron 200 pesos',
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.status', 'rejected');
        });

        it('writes a state_log entry with reason and note', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/reject", [
                    'reason' => 'invalid_reference',
                    'note' => 'No coincide la referencia bancaria',
                ])
                ->assertStatus(200);

            $this->assertDatabaseHas('payment_state_transitions', [
                'payment_id' => $payment->id,
                'event' => 'rejected',
                'reason' => 'invalid_reference',
                'note' => 'No coincide la referencia bancaria',
            ]);
        });

        it('returns 409 when the payment is already rejected', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->rejected()->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/reject", [
                    'reason' => 'other',
                ])
                ->assertStatus(409);
        });

        it('returns 422 when reason is missing', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/reject", [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['reason']);
        });

        it('returns 422 when reason is not in the allowed enum', function () {
            $payment = PaymentModel::factory()->forTenant($this->tenantA)->forSchool($this->schoolA)->create();

            $this->actingAs($this->staff)
                ->postJson("/api/staff/treasury/payments/{$payment->uuid}/reject", [
                    'reason' => 'made_up_reason',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['reason']);
        });
    });
});
