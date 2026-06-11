<?php

use App\Models\Role;
use App\Models\School as SchoolModel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Complete step 1 (company data) for the current tenant owner.
 *
 * @param  array<string, mixed>  $overrides
 */
function completeCompanyStep(TestCase $test, array $overrides = []): void
{
    $test->actingAs($test->owner)
        ->postJson('/api/onboarding/steps/company', array_merge([
            'business_name' => 'Colegio Demo',
            'rfc' => 'ABC123456XYZ',
            'fiscal_address' => [
                'street' => 'Av. Reforma',
                'exterior_number' => '100',
                'interior_number' => 'A',
                'neighborhood' => 'Centro',
                'municipality' => 'CDMX',
                'state' => 'CDMX',
                'postal_code' => '06000',
                'country' => 'MX',
            ],
            'primary_contact_name' => 'Juan Pérez',
            'primary_contact_email' => 'juan@demo.mx',
            'primary_contact_phone' => '5551234567',
        ], $overrides));
}

/**
 * Complete step 2 (branding) for the current tenant owner.
 *
 * @param  array<string, mixed>  $overrides
 */
function completeBrandingStep(TestCase $test, array $overrides = []): void
{
    $test->actingAs($test->owner)
        ->postJson('/api/onboarding/steps/branding', array_merge([
            'logo_url' => 'https://example.com/logo.png',
            'primary_color' => '#FF5733',
            'secondary_color' => '#3366FF',
        ], $overrides));
}

describe('OnboardingController', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::find($this->tenant->owner_id);
        $this->withHeaders(['X-Tenant-Slug' => $this->tenant->slug]);
    });

    // ---------------------------------------------------------------------------
    // GET /api/onboarding/progress
    // ---------------------------------------------------------------------------

    describe('GET /api/onboarding/progress', function () {
        it('returns the existing progress for the owner', function () {
            $response = $this->actingAs($this->owner)
                ->getJson('/api/onboarding/progress');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'uuid',
                        'current_step',
                        'status',
                        'steps' => [
                            '*' => ['step', 'name', 'status', 'completed_at'],
                        ],
                        'grace_period_ends_at',
                        'is_grace_period_expired',
                        'can_access_full_panel',
                        'created_at',
                        'updated_at',
                    ],
                ]);
        });

        it('auto-bootstraps for legacy tenants without a progress row', function () {
            // Ensure no progress row exists before the request
            $this->assertDatabaseMissing('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
            ]);

            $this->actingAs($this->owner)
                ->getJson('/api/onboarding/progress')
                ->assertStatus(200);

            $this->assertDatabaseHas('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
            ]);
        });

        it('returns suspended status when grace period expired and not completed', function () {
            // Trigger auto-bootstrap first
            $this->actingAs($this->owner)->getJson('/api/onboarding/progress');

            // Expire the grace period directly in the DB
            DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->update(['grace_period_ends_at' => now()->subDay()]);

            $response = $this->actingAs($this->owner)
                ->getJson('/api/onboarding/progress');

            $response->assertStatus(200);
            expect($response->json('data.status'))->toBe('suspended');

            // The DB row must still hold 'in_progress' — the suspended status is computed
            $this->assertDatabaseHas('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
                'status' => 'in_progress',
            ]);
        });

        it('returns 403 for non-owner users in the same tenant', function () {
            // Create a regular user associated to the tenant via role assignment
            $nonOwner = User::factory()->create();
            $role = Role::factory()
                ->forTenant($this->tenant)
                ->atLevel(5)
                ->create(['slug' => 'gestor_test']);
            UserRoleAssignment::factory()
                ->forUser($nonOwner)
                ->forRole($role)
                ->active()
                ->create();

            $this->actingAs($nonOwner)
                ->getJson('/api/onboarding/progress')
                ->assertStatus(403)
                ->assertJsonPath('message', 'Only the owner can perform onboarding');
        });

        it('returns 401 for unauthenticated requests', function () {
            $this->getJson('/api/onboarding/progress')
                ->assertStatus(401);
        });
    });

    // ---------------------------------------------------------------------------
    // POST /api/onboarding/steps/company
    // ---------------------------------------------------------------------------

    describe('POST /api/onboarding/steps/company', function () {
        it('completes step 1 and advances current_step to 2', function () {
            $response = $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'Colegio Demo',
                    'rfc' => 'ABC123456XYZ',
                    'fiscal_address' => [
                        'street' => 'Av. Reforma',
                        'exterior_number' => '100',
                        'interior_number' => 'A',
                        'neighborhood' => 'Centro',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06000',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'Juan Pérez',
                    'primary_contact_email' => 'juan@demo.mx',
                    'primary_contact_phone' => '5551234567',
                ]);

            $response->assertStatus(200);

            // Tenant columns must be updated
            $this->assertDatabaseHas('tenants', [
                'id' => $this->tenant->id,
                'legal_name' => 'Colegio Demo',
                'rfc' => 'ABC123456XYZ',
                'contact_name' => 'Juan Pérez',
            ]);

            // Progress must advance to step 2
            $this->assertDatabaseHas('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
                'current_step' => 2,
            ]);

            // Step 1 must be completed
            $progress = DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->first();

            $this->assertDatabaseHas('onboarding_step_status', [
                'progress_id' => $progress->id,
                'step' => 1,
                'status' => 'completed',
            ]);

            // Step 2 must be in_progress
            $this->assertDatabaseHas('onboarding_step_status', [
                'progress_id' => $progress->id,
                'step' => 2,
                'status' => 'in_progress',
            ]);
        });

        it('is idempotent: re-submitting after completion updates tenant data but keeps completed_at', function () {
            // First submission
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'First Name',
                    'rfc' => 'ABC123456XYZ',
                    'fiscal_address' => [
                        'street' => 'Av. Reforma',
                        'exterior_number' => '100',
                        'interior_number' => null,
                        'neighborhood' => 'Centro',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06000',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'First Contact',
                    'primary_contact_email' => 'first@demo.mx',
                    'primary_contact_phone' => '5551234567',
                ]);

            $progress = DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->first();

            $originalCompletedAt = DB::table('onboarding_step_status')
                ->where('progress_id', $progress->id)
                ->where('step', 1)
                ->value('completed_at');

            // Second submission with different data
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'Second Name',
                    'rfc' => 'XYZ987654ABC',
                    'fiscal_address' => [
                        'street' => 'Av. Insurgentes',
                        'exterior_number' => '200',
                        'interior_number' => null,
                        'neighborhood' => 'Roma',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06700',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'Second Contact',
                    'primary_contact_email' => 'second@demo.mx',
                    'primary_contact_phone' => '5559876543',
                ])
                ->assertStatus(200);

            // Tenant data updated with second submission
            $this->assertDatabaseHas('tenants', [
                'id' => $this->tenant->id,
                'legal_name' => 'Second Name',
                'rfc' => 'XYZ987654ABC',
                'contact_name' => 'Second Contact',
            ]);

            // completed_at must not have changed
            $newCompletedAt = DB::table('onboarding_step_status')
                ->where('progress_id', $progress->id)
                ->where('step', 1)
                ->value('completed_at');

            expect($newCompletedAt)->toBe($originalCompletedAt);
        });

        it('returns 422 for invalid RFC format', function () {
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'Colegio Demo',
                    'rfc' => 'invalid',
                    'fiscal_address' => [
                        'street' => 'Av. Reforma',
                        'exterior_number' => '100',
                        'interior_number' => null,
                        'neighborhood' => 'Centro',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06000',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'Juan Pérez',
                    'primary_contact_email' => 'juan@demo.mx',
                    'primary_contact_phone' => '5551234567',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['rfc']);
        });

        it('returns 422 for invalid email', function () {
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'Colegio Demo',
                    'rfc' => 'ABC123456XYZ',
                    'fiscal_address' => [
                        'street' => 'Av. Reforma',
                        'exterior_number' => '100',
                        'interior_number' => null,
                        'neighborhood' => 'Centro',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06000',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'Juan Pérez',
                    'primary_contact_email' => 'not-an-email',
                    'primary_contact_phone' => '5551234567',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['primary_contact_email']);
        });

        it('returns 403 for non-owner users', function () {
            $nonOwner = User::factory()->create();
            $role = Role::factory()
                ->forTenant($this->tenant)
                ->atLevel(5)
                ->create(['slug' => 'gestor_company_test']);
            UserRoleAssignment::factory()
                ->forUser($nonOwner)
                ->forRole($role)
                ->active()
                ->create();

            $this->actingAs($nonOwner)
                ->postJson('/api/onboarding/steps/company', [
                    'business_name' => 'Colegio Demo',
                    'rfc' => 'ABC123456XYZ',
                    'fiscal_address' => [
                        'street' => 'Av. Reforma',
                        'exterior_number' => '100',
                        'interior_number' => null,
                        'neighborhood' => 'Centro',
                        'municipality' => 'CDMX',
                        'state' => 'CDMX',
                        'postal_code' => '06000',
                        'country' => 'MX',
                    ],
                    'primary_contact_name' => 'Juan Pérez',
                    'primary_contact_email' => 'juan@demo.mx',
                    'primary_contact_phone' => '5551234567',
                ])
                ->assertStatus(403);
        });
    });

    // ---------------------------------------------------------------------------
    // POST /api/onboarding/steps/branding
    // ---------------------------------------------------------------------------

    describe('POST /api/onboarding/steps/branding', function () {
        it('completes step 2 after step 1 is done', function () {
            // Complete step 1 first
            completeCompanyStep($this);

            $response = $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo.png',
                    'primary_color' => '#FF5733',
                    'secondary_color' => '#3366FF',
                ]);

            $response->assertStatus(200);

            // Tenant branding JSONB column must contain logo_url
            $this->assertDatabaseHas('tenants', [
                'id' => $this->tenant->id,
                'branding->logo_url' => 'https://example.com/logo.png',
            ]);

            $progress = DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->first();

            // Step 2 must be completed
            $this->assertDatabaseHas('onboarding_step_status', [
                'progress_id' => $progress->id,
                'step' => 2,
                'status' => 'completed',
            ]);

            // current_step must advance to 3
            $this->assertDatabaseHas('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
                'current_step' => 3,
            ]);

            // Step 3 must be in_progress
            $this->assertDatabaseHas('onboarding_step_status', [
                'progress_id' => $progress->id,
                'step' => 3,
                'status' => 'in_progress',
            ]);
        });

        it('returns 422 when current_step is still 1 (out of order)', function () {
            // Do NOT complete step 1 — step is still 1
            $this->actingAs($this->owner)->getJson('/api/onboarding/progress'); // bootstrap

            $response = $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo.png',
                    'primary_color' => '#FF5733',
                    'secondary_color' => '#3366FF',
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('message', 'Cannot complete a step out of order');
        });

        it('returns 422 for invalid hex color', function () {
            completeCompanyStep($this);

            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo.png',
                    'primary_color' => '#GGGGGG',
                    'secondary_color' => '#3366FF',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['primary_color']);
        });

        it('accepts the request when logo_url is omitted (optional)', function () {
            completeCompanyStep($this);

            $response = $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'primary_color' => '#FF5733',
                    'secondary_color' => '#3366FF',
                ]);

            $response->assertStatus(200);

            // branding is persisted with logo_url = null
            $this->assertDatabaseHas('tenants', [
                'id' => $this->tenant->id,
                'branding->primary_color' => '#FF5733',
                'branding->logo_url' => null,
            ]);
        });

        it('persists branding as JSON in tenants.branding column', function () {
            completeCompanyStep($this);

            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo.png',
                    'primary_color' => '#FF5733',
                    'secondary_color' => '#3366FF',
                ])
                ->assertStatus(200);

            $tenant = DB::table('tenants')->where('id', $this->tenant->id)->first();
            $branding = json_decode($tenant->branding, true);

            expect($branding)->toMatchArray([
                'logo_url' => 'https://example.com/logo.png',
                'primary_color' => '#FF5733',
                'secondary_color' => '#3366FF',
            ]);
        });

        it('is idempotent: re-submitting updates branding but keeps step 2 completed_at', function () {
            completeCompanyStep($this);

            // First branding submission
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo-v1.png',
                    'primary_color' => '#FF5733',
                    'secondary_color' => '#3366FF',
                ]);

            $progress = DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->first();

            $originalCompletedAt = DB::table('onboarding_step_status')
                ->where('progress_id', $progress->id)
                ->where('step', 2)
                ->value('completed_at');

            // Second branding submission with different logo
            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/branding', [
                    'logo_url' => 'https://example.com/logo-v2.png',
                    'primary_color' => '#AABBCC',
                    'secondary_color' => '#112233',
                ])
                ->assertStatus(200);

            $this->assertDatabaseHas('tenants', [
                'id' => $this->tenant->id,
                'branding->logo_url' => 'https://example.com/logo-v2.png',
            ]);

            $newCompletedAt = DB::table('onboarding_step_status')
                ->where('progress_id', $progress->id)
                ->where('step', 2)
                ->value('completed_at');

            expect($newCompletedAt)->toBe($originalCompletedAt);
        });
    });

    // ---------------------------------------------------------------------------
    // POST /api/onboarding/steps/first-school
    // ---------------------------------------------------------------------------

    describe('POST /api/onboarding/steps/first-school', function () {
        it('completes step 3 and marks progress as completed', function () {
            completeCompanyStep($this);
            completeBrandingStep($this);

            $school = SchoolModel::factory()->for($this->tenant)->create();

            $response = $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/first-school', [
                    'school_id' => $school->uuid,
                ]);

            $response->assertStatus(200);

            // Progress must be completed
            $this->assertDatabaseHas('onboarding_progress', [
                'tenant_id' => $this->tenant->id,
                'status' => 'completed',
            ]);

            $progress = DB::table('onboarding_progress')
                ->where('tenant_id', $this->tenant->id)
                ->first();

            // Step 3 must be completed
            $this->assertDatabaseHas('onboarding_step_status', [
                'progress_id' => $progress->id,
                'step' => 3,
                'status' => 'completed',
            ]);

            // current_step stays at 3
            expect($progress->current_step)->toBe(3);
        });

        it('returns 403 when school belongs to a different tenant', function () {
            completeCompanyStep($this);
            completeBrandingStep($this);

            $otherTenant = Tenant::factory()->create();
            $foreignSchool = SchoolModel::factory()->for($otherTenant)->create();

            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/first-school', [
                    'school_id' => $foreignSchool->uuid,
                ])
                ->assertStatus(403)
                ->assertJsonPath('message', 'School not found in your tenant');
        });

        it('returns 422 when current_step is still less than 3 (out of order)', function () {
            // Only complete step 1 — step 2 not yet done, so current_step = 2
            completeCompanyStep($this);

            $school = SchoolModel::factory()->for($this->tenant)->create();

            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/first-school', [
                    'school_id' => $school->uuid,
                ])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Cannot complete a step out of order');
        });

        it('returns 422 for invalid uuid format', function () {
            completeCompanyStep($this);
            completeBrandingStep($this);

            $this->actingAs($this->owner)
                ->postJson('/api/onboarding/steps/first-school', [
                    'school_id' => 'not-a-valid-uuid',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['school_id']);
        });
    });

});
