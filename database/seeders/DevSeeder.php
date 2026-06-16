<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentStateTransition;
use App\Models\Role;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\Tenant;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $superadminRole = Role::where('slug', 'superadmin')->whereNull('tenant_id')->first();

        foreach ($staffAccounts as $account) {
            $staffUser = User::firstOrCreate(
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

            if ($superadminRole !== null) {
                UserRoleAssignment::firstOrCreate(
                    [
                        'user_id' => $staffUser->id,
                        'role_id' => $superadminRole->id,
                        'school_id' => null,
                        'revoked_at' => null,
                    ],
                );
            }
        }

        // -------------------------------------------------------
        // Backoffice Superadmins — TWO dedicated example accounts with the
        // `superadmin` role. Two are needed for the dual-control ceremony:
        // one proposes a new superadmin and a DIFFERENT one approves it
        // (proposer != approver).
        // Login: POST /api/staff/auth/login  (password: "password")
        //   superadmin@kibi.com   · superadmin2@kibi.com
        // -------------------------------------------------------
        $superadminRole = Role::where('slug', 'superadmin')->whereNull('tenant_id')->first();

        $superadminAccounts = [
            ['email' => 'superadmin@kibi.com', 'first_name' => 'Super', 'last_name_paternal' => 'Admin'],
            ['email' => 'superadmin2@kibi.com', 'first_name' => 'Super', 'last_name_paternal' => 'Admin Dos'],
        ];

        foreach ($superadminAccounts as $account) {
            $superadmin = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'is_staff' => true,
                    'first_name' => $account['first_name'],
                    'last_name_paternal' => $account['last_name_paternal'],
                    'last_name_maternal' => null,
                    'password_hash' => Hash::make('password'),
                    'status' => 'active',
                ]
            );

            if ($superadminRole !== null) {
                UserRoleAssignment::firstOrCreate([
                    'user_id' => $superadmin->id,
                    'role_id' => $superadminRole->id,
                    'school_id' => null,
                    'revoked_at' => null,
                ]);
            }
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

        $demoSchool = School::firstOrCreate(
            ['slug' => 'demo-escuela'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Escuela Demo',
                'status' => 'active',
            ]
        );

        // Ensure tenant_id is always set on the owner (firstOrCreate won't update if already exists)
        $ownerUser->tenant_id = $tenant->id;
        $ownerUser->save();

        $ownerRole = Role::firstOrCreate(
            ['slug' => 'owner', 'tenant_id' => null],
            ['name' => 'Owner', 'hierarchy_level' => 1, 'is_system_role' => true],
        );

        UserRoleAssignment::firstOrCreate(
            [
                'user_id' => $ownerUser->id,
                'role_id' => $ownerRole->id,
                'school_id' => null,
                'revoked_at' => null,
            ],
            ['assigned_at' => now(), 'assigned_by' => null],
        );

        // -------------------------------------------------------
        // Demo director — has user.create + user.view
        // Login: POST /api/auth/login  (header X-Tenant-Slug: demo)
        // -------------------------------------------------------
        $directorUser = User::firstOrCreate(
            ['email' => 'director@colegiodemo.mx'],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'is_staff' => false,
                'first_name' => 'Director',
                'last_name_paternal' => 'Demo',
                'last_name_maternal' => null,
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        $directorUser->tenant_id = $tenant->id;
        $directorUser->save();

        $directorRole = Role::firstOrCreate(
            ['slug' => 'director', 'tenant_id' => null],
            ['name' => 'Director', 'hierarchy_level' => 5, 'is_system_role' => false],
        );

        UserRoleAssignment::firstOrCreate(
            [
                'user_id' => $directorUser->id,
                'role_id' => $directorRole->id,
                'school_id' => $demoSchool->id,
                'revoked_at' => null,
            ],
            ['assigned_at' => now(), 'assigned_by' => null],
        );

        // -------------------------------------------------------
        // Demo students — enrolled in demo-escuela
        // -------------------------------------------------------
        $studentRole = Role::firstOrCreate(
            ['slug' => 'student', 'tenant_id' => null],
            ['name' => 'Student', 'hierarchy_level' => 9, 'is_system_role' => false],
        );

        $studentUsers = [];

        if ($demoSchool !== null) {
            $demoStudents = [
                [
                    'email' => 'student1@colegiodemo.mx',
                    'first_name' => 'Ana',
                    'last_name_paternal' => 'García',
                    'last_name_maternal' => 'López',
                    'enrollment_number' => 'EN-001',
                    'gender' => 'female',
                ],
                [
                    'email' => 'student2@colegiodemo.mx',
                    'first_name' => 'Carlos',
                    'last_name_paternal' => 'Martínez',
                    'last_name_maternal' => 'Ruiz',
                    'enrollment_number' => 'EN-002',
                    'gender' => 'male',
                ],
                [
                    'email' => 'student3@colegiodemo.mx',
                    'first_name' => 'Sofía',
                    'last_name_paternal' => 'Hernández',
                    'last_name_maternal' => null,
                    'enrollment_number' => 'EN-003',
                    'gender' => 'female',
                ],
            ];

            foreach ($demoStudents as $data) {
                $studentUser = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'is_staff' => false,
                        'first_name' => $data['first_name'],
                        'last_name_paternal' => $data['last_name_paternal'],
                        'last_name_maternal' => $data['last_name_maternal'],
                        'status' => 'pending',
                    ]
                );

                UserRoleAssignment::firstOrCreate(
                    [
                        'user_id' => $studentUser->id,
                        'role_id' => $studentRole->id,
                        'school_id' => $demoSchool->id,
                        'revoked_at' => null,
                    ],
                    ['assigned_at' => now(), 'assigned_by' => null],
                );

                StudentProfile::firstOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'enrollment_number' => $data['enrollment_number'],
                        'gender' => $data['gender'],
                    ]
                );

                $studentUsers[$data['email']] = $studentUser;
            }
        }

        // -------------------------------------------------------
        // Demo tutors — enrolled in demo-escuela
        // -------------------------------------------------------
        $tutorRole = Role::where('slug', 'tutor')->whereNull('tenant_id')->first();

        $demoTutors = [
            [
                'email' => 'tutor1@colegiodemo.mx',
                'first_name' => 'María',
                'last_name_paternal' => 'González',
                'last_name_maternal' => 'Pérez',
                'occupation' => 'Contadora',
            ],
            [
                'email' => 'tutor2@colegiodemo.mx',
                'first_name' => 'Roberto',
                'last_name_paternal' => 'Sánchez',
                'last_name_maternal' => 'Torres',
                'occupation' => 'Ingeniero',
            ],
        ];

        $tutorUsers = [];

        foreach ($demoTutors as $data) {
            $tutorUser = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'is_staff' => false,
                    'first_name' => $data['first_name'],
                    'last_name_paternal' => $data['last_name_paternal'],
                    'last_name_maternal' => $data['last_name_maternal'],
                    'password_hash' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );

            if ($tutorRole !== null) {
                UserRoleAssignment::firstOrCreate(
                    [
                        'user_id' => $tutorUser->id,
                        'role_id' => $tutorRole->id,
                        'school_id' => $demoSchool->id,
                        'revoked_at' => null,
                    ],
                    ['assigned_at' => now(), 'assigned_by' => null],
                );
            }

            TutorProfile::firstOrCreate(
                ['user_id' => $tutorUser->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'occupation' => $data['occupation'],
                ]
            );

            $tutorUsers[$data['email']] = $tutorUser;
        }

        // -------------------------------------------------------
        // Tutor-student links
        // tutor1 → student1, tutor1 → student2, tutor2 → student3
        // -------------------------------------------------------
        $links = [
            ['tutor' => 'tutor1@colegiodemo.mx', 'student' => 'student1@colegiodemo.mx', 'relationship' => 'mother'],
            ['tutor' => 'tutor1@colegiodemo.mx', 'student' => 'student2@colegiodemo.mx', 'relationship' => 'father'],
            ['tutor' => 'tutor2@colegiodemo.mx', 'student' => 'student3@colegiodemo.mx', 'relationship' => 'guardian'],
        ];

        foreach ($links as $link) {
            $tutorUser = $tutorUsers[$link['tutor']] ?? null;
            $studentUser = $studentUsers[$link['student']] ?? null;

            if ($tutorUser === null || $studentUser === null) {
                continue;
            }

            $exists = DB::table('student_tutors')
                ->where('tutor_user_id', $tutorUser->id)
                ->where('student_user_id', $studentUser->id)
                ->whereNull('unlinked_at')
                ->exists();

            if (! $exists) {
                DB::table('student_tutors')->insert([
                    'tutor_user_id' => $tutorUser->id,
                    'student_user_id' => $studentUser->id,
                    'relationship' => $link['relationship'],
                    'linked_at' => now(),
                ]);
            }
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
