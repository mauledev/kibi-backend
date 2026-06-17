<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissionCategories();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();
    }

    private function seedPermissionCategories(): void
    {
        $categories = [
            ['scope' => 'staff', 'name' => 'support'],
            ['scope' => 'staff', 'name' => 'finance'],
        ];

        foreach ($categories as $category) {
            DB::table('permission_categories')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'scope' => $category['scope'],
                'name' => $category['name'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $catId = fn (string $name): int => (int) DB::table('permission_categories')
            ->where('scope', 'staff')
            ->where('name', $name)
            ->value('id');

        $permissions = [
            // staff/finance — Softlinkia treasury (SaaS billing, distinct from school payment.*)
            ['category' => 'finance', 'name' => 'View SaaS billing',           'slug' => 'billing.view'],
            ['category' => 'finance', 'name' => 'Approve SaaS payments',       'slug' => 'billing.approve'],
            ['category' => 'finance', 'name' => 'Refund SaaS payments',        'slug' => 'billing.refund'],
            ['category' => 'finance', 'name' => 'Review SaaS payments',        'slug' => 'billing.review'],
            ['category' => 'finance', 'name' => 'Return payment to operator',  'slug' => 'billing.return'],
            ['category' => 'finance', 'name' => 'View billing metrics',        'slug' => 'billing.metrics'],
            ['category' => 'finance', 'name' => 'Generate Owner remittances',  'slug' => 'remittance.create'],
            ['category' => 'finance', 'name' => 'Assign batches to operators', 'slug' => 'batch.assign'],
            ['category' => 'finance', 'name' => 'View audit log',              'slug' => 'audit.view'],

            // staff/support — Softlinkia support (tickets + temporary tenant linking)
            ['category' => 'support', 'name' => 'View tickets',            'slug' => 'ticket.view'],
            ['category' => 'support', 'name' => 'Create tickets',          'slug' => 'ticket.create'],
            ['category' => 'support', 'name' => 'Resolve tickets',         'slug' => 'ticket.resolve'],
            ['category' => 'support', 'name' => 'Escalate tickets',        'slug' => 'ticket.escalate'],
            ['category' => 'support', 'name' => 'Temporary tenant linking', 'slug' => 'tenant.impersonate'],
            ['category' => 'support', 'name' => 'View tenants',            'slug' => 'tenant.view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'category_id' => $catId($permission['category']),
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRoles(): void
    {
        $catId = fn (string $name): ?int => DB::table('permission_categories')
            ->where('scope', 'staff')
            ->where('name', $name)
            ->value('id');

        $roles = [
            [
                'category_id' => null,
                'name' => 'Superadmin',
                'slug' => 'superadmin',
                'hierarchy_level' => 1,
                'requires_2fa' => true,
            ],
            [
                'category_id' => $catId('finance'),
                'name' => 'Treasury Leader',
                'slug' => 'leader',
                'hierarchy_level' => 2,
                'requires_2fa' => true,
            ],
            [
                'category_id' => $catId('finance'),
                'name' => 'Treasury Operator',
                'slug' => 'operator',
                'hierarchy_level' => 3,
                'requires_2fa' => false,
            ],
            [
                'category_id' => $catId('support'),
                'name' => 'Support',
                'slug' => 'support',
                'hierarchy_level' => 3,
                'requires_2fa' => true,
            ],
            // Not used in MVP (Superadmin handles all treasury work) but kept
            // seeded for forward-compat once the Leader/Operator separation lands.
            [
                'category_id' => null,
                'name' => 'Treasury Operator',
                'slug' => 'treasury_operator',
                'hierarchy_level' => 2,
                'requires_2fa' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => null,
                'category_id' => $role['category_id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'hierarchy_level' => $role['hierarchy_level'],
                'is_system_role' => true,
                'requires_2fa' => $role['requires_2fa'],
                'created_at' => now(),
            ]);
        }
    }

    private function seedRolePermissions(): void
    {
        $permissionId = fn (string $slug): int => (int) DB::table('permissions')->where('slug', $slug)->value('id');
        $roleId = fn (string $slug): int => (int) DB::table('roles')->where('slug', $slug)->whereNull('tenant_id')->value('id');

        $assign = function (string $roleSlug, array $permissionSlugs) use ($roleId, $permissionId): void {
            $rid = $roleId($roleSlug);
            foreach ($permissionSlugs as $slug) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $rid,
                    'permission_id' => $permissionId($slug),
                ]);
            }
        };

        $assign('operator', [
            'billing.view',
            'billing.approve',
            'remittance.create',
        ]);

        $assign('leader', [
            'billing.view',
            'billing.approve',
            'remittance.create',
            'billing.refund',
            'billing.review',
            'billing.return',
            'batch.assign',
            'billing.metrics',
            'audit.view',
        ]);

        $assign('support', [
            'ticket.view',
            'ticket.create',
            'ticket.resolve',
            'ticket.escalate',
            'tenant.impersonate',
            'tenant.view',
        ]);
    }
}
