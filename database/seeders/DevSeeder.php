<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentStateTransition;
use App\Models\Role;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // Softlinkia staff (is_staff = true)
        // Login: POST /api/staff/auth/login
        // -------------------------------------------------------
        $staffAccounts = [
            ['first_name' => 'Fernando', 'last_name_paternal' => 'Brayan', 'last_name_maternal' => null, 'email' => 'fernando.bryan.m.g@gmail.com'],
            ['first_name' => 'Tadeo', 'last_name_paternal' => 'Andrade', 'last_name_maternal' => null, 'email' => 'andradet.dev@gmail.com'],
            ['first_name' => 'Mauricio', 'last_name_paternal' => 'Ledesma', 'last_name_maternal' => null, 'email' => 'mauledc@gmail.com'],
            ['first_name' => 'Damian', 'last_name_paternal' => 'Palomo', 'last_name_maternal' => null, 'email' => 'pedrodamian411@gmail.com'],
            ['first_name' => 'Jesus', 'last_name_paternal' => 'Soto', 'last_name_maternal' => null, 'email' => 'soto.tovar.jesus@gmail.com'],
            ['first_name' => 'Fernando', 'last_name_paternal' => 'Manuel', 'last_name_maternal' => null, 'email' => 'fernandomanuel640@gmail.com'],
            ['first_name' => 'Erick', 'last_name_paternal' => 'Erick', 'last_name_maternal' => null, 'email' => 'eriktrabajo24@gmail.com'],
        ];

        foreach ($staffAccounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'is_staff' => true,
                    'first_name' => $account['first_name'],
                    'last_name_paternal' => $account['last_name_paternal'],
                    'last_name_maternal' => $account['last_name_maternal'],
                    'password_hash' => Hash::make('password'),
                    'status' => 'active',
                ]
            );
        }

        // -------------------------------------------------------
        // Demo tenant — for testing tenant auth flow
        // Login: POST /api/auth/login  (header X-Tenant-Slug: demo)
        // -------------------------------------------------------

        // Create the owner first; tenant references users.id so user must exist
        $ownerUser = User::firstOrCreate(
            ['email' => 'owner@colegiodemo.mx'],
            [
                'is_staff' => false,
                'first_name' => 'Owner',
                'last_name_paternal' => 'Demo',
                'last_name_maternal' => null,
                'password_hash' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'owner_id' => $ownerUser->id,
                'name' => 'Colegio Demo',
                'legal_name' => 'Colegio Demo S.A. de C.V.',
                'contact_email' => 'admin@colegiodemo.mx',
                'status' => 'active',
            ]
        );

        School::firstOrCreate(
            ['slug' => 'demo-escuela'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Escuela Demo',
                'status' => 'active',
            ]
        );

        $ownerUser = User::firstOrCreate(
            ['email' => 'owner@colegiodemo.mx'],
            [
                'tenant_id' => $tenant->id,
                'full_name' => 'Owner Demo',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $ownerRole = Role::where('slug', 'owner')->whereNull('tenant_id')->first();

        if ($ownerRole !== null) {
            UserRoleAssignment::firstOrCreate(
                [
                    'user_id' => $ownerUser->id,
                    'role_id' => $ownerRole->id,
                    'school_id' => null,
                    'revoked_at' => null,
                ],
            );
        }

        $this->seedTreasuryPayments($tenant->id, $ownerUser->id);
    }

    /**
     * Seed a small dataset of payments for the demo tenant so the Treasury
     * bandeja has data to display end-to-end. Idempotent via reference.
     */
    private function seedTreasuryPayments(int $tenantId, int $actorUserId): void
    {
        $school = School::where('slug', 'demo-escuela')->first();

        if ($school === null) {
            return;
        }

        $samples = [
            ['ref' => 'DEMO-PAY-0001', 'payer' => 'Juan Pérez',     'amount' => 150_000, 'status' => 'pending',  'paid_at' => now()->subDays(2)],
            ['ref' => 'DEMO-PAY-0002', 'payer' => 'María López',    'amount' => 200_000, 'status' => 'pending',  'paid_at' => now()->subDays(1)],
            ['ref' => 'DEMO-PAY-0003', 'payer' => 'Carlos Ramírez', 'amount' => 120_000, 'status' => 'pending',  'paid_at' => now()->subHours(6)],
            ['ref' => 'DEMO-PAY-0004', 'payer' => 'Ana García',     'amount' => 175_000, 'status' => 'approved', 'paid_at' => now()->subDays(5), 'received' => 175_000],
            ['ref' => 'DEMO-PAY-0005', 'payer' => 'Luis Martínez',  'amount' => 95_000,  'status' => 'rejected', 'paid_at' => now()->subDays(7)],
        ];

        foreach ($samples as $sample) {
            $payment = Payment::firstOrCreate(
                ['reference' => $sample['ref']],
                [
                    'tenant_id' => $tenantId,
                    'school_id' => $school->id,
                    'status' => $sample['status'],
                    'payer_name' => $sample['payer'],
                    'amount_cents' => $sample['amount'],
                    'received_amount_cents' => $sample['received'] ?? null,
                    'currency' => 'MXN',
                    'paid_at' => $sample['paid_at'],
                ],
            );

            // Append the initial "created" log entry if missing.
            PaymentStateTransition::firstOrCreate(
                [
                    'payment_id' => $payment->id,
                    'event' => 'created',
                ],
                [
                    'from_status' => null,
                    'to_status' => 'pending',
                    'actor_user_id' => null,
                    'actor_name' => 'System',
                    'reason' => null,
                    'note' => null,
                    'created_at' => $payment->created_at ?? now(),
                ],
            );

            // For approved/rejected samples, add the matching transition.
            if ($sample['status'] === 'approved') {
                PaymentStateTransition::firstOrCreate(
                    ['payment_id' => $payment->id, 'event' => 'approved'],
                    [
                        'from_status' => 'pending',
                        'to_status' => 'approved',
                        'actor_user_id' => $actorUserId,
                        'actor_name' => 'Demo Seeder',
                        'reason' => null,
                        'note' => 'Conciliado con extracto bancario',
                        'created_at' => $sample['paid_at']->copy()->addHour(),
                    ],
                );
            } elseif ($sample['status'] === 'rejected') {
                PaymentStateTransition::firstOrCreate(
                    ['payment_id' => $payment->id, 'event' => 'rejected'],
                    [
                        'from_status' => 'pending',
                        'to_status' => 'rejected',
                        'actor_user_id' => $actorUserId,
                        'actor_name' => 'Demo Seeder',
                        'reason' => 'amount_mismatch',
                        'note' => 'Faltaron 200 pesos en la transferencia',
                        'created_at' => $sample['paid_at']->copy()->addHour(),
                    ],
                );
            }
        }
    }
}
