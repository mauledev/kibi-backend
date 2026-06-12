<?php

namespace Database\Seeders;

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
    }
}
