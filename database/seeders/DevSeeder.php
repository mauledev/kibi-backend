<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
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
        // Demo students — enrolled in demo-escuela
        // -------------------------------------------------------
        $demoSchool = School::where('slug', 'demo-escuela')->first();
        $studentRole = Role::firstOrCreate(
            ['slug' => 'student', 'tenant_id' => null],
            ['name' => 'Student', 'hierarchy_level' => 9, 'is_system_role' => false],
        );

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
                );

                StudentProfile::firstOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'enrollment_number' => $data['enrollment_number'],
                        'gender' => $data['gender'],
                    ]
                );
            }
        }
    }
}
