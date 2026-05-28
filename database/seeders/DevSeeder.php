<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // Softlinkia staff (tenant_id IS NULL)
        // Login: POST /api/staff/auth/login
        // -------------------------------------------------------
        $staffAccounts = [
            ['full_name' => 'Fernando Brayan', 'email' => 'fernando.bryan.m.g@gmail.com'],
            ['full_name' => 'Tadeo Andrade', 'email' => 'andradet.dev@gmail.com'],
            ['full_name' => 'Mauricio Ledesma', 'email' => 'mauledc@gmail.com'],
            ['full_name' => 'Damian Palomo', 'email' => 'pedrodamian411@gmail.com'],
            ['full_name' => 'Jesus Soto', 'email' => 'soto.tovar.jesus@gmail.com'],
            ['full_name' => 'Fernando Manuel', 'email' => 'fernandomanuel640@gmail.com'],
            ['full_name' => 'Erick ', 'email' => 'eriktrabajo24@gmail.com'],
        ];

        foreach ($staffAccounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'tenant_id' => null,
                    'full_name' => $account['full_name'],
                    'password_hash' => Hash::make('password'),
                    'status' => 'active',
                ]
            );
        }

        // -------------------------------------------------------
        // Demo tenant — for testing tenant auth flow
        // Login: POST /api/auth/login  (header X-Tenant-Slug: demo)
        // -------------------------------------------------------
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
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

        User::firstOrCreate(
            ['email' => 'owner@colegiodemo.mx'],
            [
                'tenant_id' => $tenant->id,
                'full_name' => 'Owner Demo',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
            ]
        );
    }
}
